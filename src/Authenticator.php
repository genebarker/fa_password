<?php

namespace madman\Password;

use DateInterval;

class Authenticator
{
    const UNEXPECTED_ERROR_MSG = (
        'An unexpected error occurred while processing your request.'
    );
    const UNKNOWN_USERNAME_MSG = (
        'This account does not exist. Please enter a different username ' .
        'or contact your system administrator.'
    );
    const BAD_PASSWORD_MSG = (
        'The password for this account is incorrect.'
    );
    const ACCOUNT_LOCKED_MSG = (
        'This account is locked. Please wait a while then try again or ' .
        'contact your system administrator.'
    );
    const PASSWORD_EXPIRED_MSG = (
        'The password for this account expired. Please provide a new one ' .
        'using the new password option on the login screen.'
    );
    const RECYCLED_PASSWORD_MSG = (
        'This password matches one of your recently used ones. Please ' .
        'create a new password.'
    );

    public $store;
    public $config;
    public $zxcvbn;

    public function __construct($store)
    {
        $this->store = $store;
        $this->config = $store->getConfig();
        $this->zxcvbn = new ZxcvbnWrapper();
    }

    public function login($username, $password, $new_password = null)
    {
        try {
            return $this->processLogin($username, $password, $new_password);
        } catch (\Exception $e) {
            return $this->processUnexpectedException($username, $e);
        }
    }

    private function processLogin($username, $password, $new_password)
    {
        $user = $this->getExtendedUser($username);
        if ($user == null) {
            $user = $this->getBaseUser($username);
            if ($user == null) {
                $has_failed = true;
                $message = self::UNKNOWN_USERNAME_MSG;
                return new Result($has_failed, $message);
            }
            if (md5($password) != $user->fa_pw_hash) {
                $has_failed = true;
                $message = self::BAD_PASSWORD_MSG;
                return new Result($has_failed, $message);
            }
            $user = $this->migrateUser($user, $password);
        }

        if (
            $user->is_locked
            && $this->tooSoonToTryAgain($user->last_pw_fail_time)
        ) {
            $has_failed = true;
            $message = self::ACCOUNT_LOCKED_MSG;
            return new Result($has_failed, $message);
        }

        if (!password_verify($password, $user->pw_hash)) {
            $user->ongoing_pw_fail_count++;
            $user->last_pw_fail_time = date_create('now');
            if ($this->lockShouldTrigger($user->ongoing_pw_fail_count)) {
                $user->is_locked = true;
            }
            $this->store->updateUser($user);
            $has_failed = true;
            $message = self::BAD_PASSWORD_MSG;
            return new Result($has_failed, $message);
        }

        if ($new_password != null) {
            return $this->processPasswordChange($username, $new_password);
        }

        if (
            $user->needs_pw_change
            || $this->passwordIsTooOld($user->last_pw_update_time)
        ) {
            $has_failed = true;
            $message = self::PASSWORD_EXPIRED_MSG;
            return new Result($has_failed, $message);
        }

        $user->is_locked = false;
        $user->ongoing_pw_fail_count = 0;
        $this->store->updateUser($user);
        $has_failed = false;
        $message = "Welcome back $username.";
        return new Result($has_failed, $message);
    }

    private function processUnexpectedException($username, $exception)
    {
        $has_failed = true;
        $message = (
            self::UNEXPECTED_ERROR_MSG .
            " Username: $username." .
            " Cause: " . $exception->getMessage()
        );
        error_log($message);
        return new Result($has_failed, $message);
    }

    private function getExtendedUser($username)
    {
        return $this->getUser('getUserByUsername', $username);
    }

    private function getUser($method_name, $username)
    {
        try {
            $method_call = array($this->store, $method_name);
            $user = call_user_func($method_call, $username);
        } catch (\Exception $e) {
            if ($e->getCode() != Datastore::NO_MATCHING_ROW_FOUND) {
                throw $e;
            }
            $user = null;
        }
        return $user;
    }

    private function getBaseUser($username)
    {
        return $this->getUser('getBaseUserByUsername', $username);
    }

    private function migrateUser($user, $password)
    {
        $is_temporary = true;
        $user = $this->updateUserFieldsForNewPassword(
            $user,
            $password,
            $is_temporary
        );
        $this->store->insertUser($user);
        $this->store->addPasswordToHistory(
            $user->oid,
            $user->pw_hash,
            date_create('now')
        );
        return $user;
    }
    
    private function tooSoonToTryAgain($last_pw_fail_time)
    {
        $lock_length = new DateInterval(
            'PT' . $this->config->login_fail_lock_minutes . 'M'
        );
        $expire_time = clone $last_pw_fail_time;
        date_add($expire_time, $lock_length);
        $now = date_create('now');

        return $now < $expire_time;
    }

    private function lockShouldTrigger($fail_count)
    {
        if (!$this->lockFeatureIsEnabled()) {
            return false;
        }
        return $fail_count > $this->config->login_fail_threshold_count;
    }

    private function lockFeatureIsEnabled()
    {
        return (
            $this->config->login_fail_threshold_count > 0
            && $this->config->login_fail_lock_minutes > 0
        );
    }

    private function processPasswordChange(
        $username,
        $new_password,
        $is_temporary = false
    ) {
        $user = $this->getExtendedUser($username);
        if ($user == null) {
            $user = $this->getBaseUser($username);
            if ($user == null) {
                $has_failed = true;
                $message = self::UNKNOWN_USERNAME_MSG;
                return new Result($has_failed, $message);
            }
            $user = $this->migrateUser($user, $new_password);
        }
        if ($this->passwordInHistory($user->oid, $new_password)) {
            $has_failed = true;
            $message = self::RECYCLED_PASSWORD_MSG;
            return new Result($has_failed, $message);
        }
        if ($this->passwordTooWeak($username, $new_password)) {
            $has_failed = true;
            $message = $this->createPasswordTooWeakMessage(
                $username,
                $new_password
            );
            return new Result($has_failed, $message);
        }
        $user = $this->updateUserFieldsForNewPassword(
            $user,
            $new_password,
            $is_temporary
        );
        $this->store->updateUser($user);
        $this->store->addPasswordToHistory(
            $user->oid,
            $user->pw_hash,
            $user->last_pw_update_time
        );
        $has_failed = false;
        $message = "Password successfully changed for $username.";
        return new Result($has_failed, $message);
    }

    private function passwordInHistory($user_oid, $password)
    {
        $limit = $this->config->password_history_count;
        $history = $this->store->getPasswordHistory($user_oid, $limit);
        foreach ($history as $pw) {
            if (password_verify($password, $pw['pw_hash'])) {
                return true;
            }
        }
        return false;
    }

    private function passwordTooWeak($username, $password)
    {
        $score = $this->zxcvbn->getPasswordScore($username, $password);
        return ($score < $this->config->minimum_password_strength);
    }

    private function createPasswordTooWeakMessage($username, $password)
    {
        return trim(
            'New password is too weak. ' .
            $this->zxcvbn->getPasswordHints($username, $password)
        );
    }

    private function updateUserFieldsForNewPassword(
        $user,
        $new_password,
        $is_temporary = false
    ) {
        $user->fa_pw_hash = md5($new_password);
        $user->pw_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $user->needs_pw_change = $is_temporary;
        $user->is_locked = false;
        $user->ongoing_pw_fail_count = 0;
        $user->last_pw_update_time = date_create('now');
        return $user;
    }

    private function passwordIsTooOld($last_pw_update_time)
    {
        $max_days_old = $this->config->maximum_password_age_days;
        if ($max_days_old == 0) {
            return false;
        }
        $duration_valid = new DateInterval('P' . $max_days_old . 'D');
        $expire_time = clone $last_pw_update_time;
        date_add($expire_time, $duration_valid);
        $now = date_create('now');

        return $now > $expire_time;
    }

    public function changePassword($username, $new_password)
    {
        try {
            return $this->processPasswordChange($username, $new_password);
        } catch (\Exception $e) {
            return $this->processUnexpectedException($username, $e);
        }
    }

    public function resetPassword($username, $temp_password)
    {
        try {
            $is_temporary = true;
            return $this->processPasswordChange(
                $username,
                $temp_password,
                $is_temporary
            );
        } catch (\Exception $e) {
            return $this->processUnexpectedException($username, $e);
        }
    }
}
