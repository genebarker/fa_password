<?php

namespace madman\Password;

use DateInterval;

class Authenticator
{
    const UNEXPECTED_ERROR_MSG = (
        'An unexpected error occurred while processing login attempt.'
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
            $has_failed = true;
            $message = (
                self::UNEXPECTED_ERROR_MSG .
                " Username: $username." .
                " Cause: " . $e->getMessage()
            );
            error_log($message);
            return new LoginAttempt($has_failed, $message);
        }
    }

    public function processLogin($username, $password, $new_password)
    {
        $user = $this->getExtendedUser($username);
        if ($user == null) {
            return $this->processNewUserLogin(
                $username,
                $password,
                $new_password
            );
        }
        if ($user->is_locked && $this->tooSoonToTryAgain($user)) {
            $has_failed = true;
            $message = self::ACCOUNT_LOCKED_MSG;
            return new LoginAttempt($has_failed, $message);
        }
        if (!password_verify($password, $user->pw_hash)) {
            $user->ongoing_pw_fail_count++;
            $user->last_pw_fail_time = date_create('now');
            if (
                $user->ongoing_pw_fail_count
                > $this->config->login_fail_threshold_count
            ) {
                $user->is_locked = true;
            }
            $this->store->updateUser($user);
            $has_failed = true;
            $message = self::BAD_PASSWORD_MSG;
            return new LoginAttempt($has_failed, $message);
        }

        if ($new_password != null) {
            if ($this->passwordTooWeak($username, $new_password)) {
                $has_failed = true;
                $message = $this->createPasswordTooWeakMessage(
                    $username,
                    $new_password
                );
                return new LoginAttempt($has_failed, $message);
            }
            $user->fa_pw_hash = md5($new_password);
            $user->pw_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $user->needs_pw_change = false;
            $this->store->addPasswordToHistory($user->oid, $user->pw_hash);
        }

        if ($user->needs_pw_change) {
            $has_failed = true;
            $message = self::PASSWORD_EXPIRED_MSG;
            return new LoginAttempt($has_failed, $message);
        }

        $user->is_locked = false;
        $user->ongoing_pw_fail_count = 0;
        $this->store->updateUser($user);
        $has_failed = false;
        $message = "Welcome back $username.";
        return new LoginAttempt($has_failed, $message);
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

    private function processNewUserLogin($username, $password, $new_password)
    {
        $user = $this->getBaseUser($username);
        if ($user == null) {
            $has_failed = true;
            $message = self::UNKNOWN_USERNAME_MSG;
            return new LoginAttempt($has_failed, $message);
        }
        if (md5($password) != $user->fa_pw_hash) {
            $has_failed = true;
            $message = self::BAD_PASSWORD_MSG;
            return new LoginAttempt($has_failed, $message);
        }
        if ($new_password == null) {
            $has_failed = true;
            $message = self::PASSWORD_EXPIRED_MSG;
            return new LoginAttempt($has_failed, $message);
        }
        if ($this->passwordTooWeak($username, $new_password)) {
            $has_failed = true;
            $message = $this->createPasswordTooWeakMessage(
                $username,
                $new_password
            );
            return new LoginAttempt($has_failed, $message);
        }

        $user->fa_pw_hash = md5($new_password);
        $user->pw_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $this->store->insertUser($user);
        $this->store->addPasswordToHistory($user->oid, $user->pw_hash);
        $has_failed = false;
        $message = "Welcome back $username.";
        return new LoginAttempt($has_failed, $message);
    }
    
    public function tooSoonToTryAgain($user)
    {
        $lock_length = new DateInterval(
            'PT' . $this->config->login_fail_lock_minutes . 'M'
        );
        $expire_time = date_add($user->last_pw_fail_time, $lock_length);
        $now = date_create('now');

        return $now < $expire_time;
    }

    public function passwordTooWeak($username, $password)
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
}
