<?php

namespace madman\Password;

use DateInterval;

class Authenticator
{
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
            $user = $this->store->getUserByUsername($username);
        } catch (\Exception $e) {
            return new LoginAttempt();
        }
        if ($user->is_locked && $this->tooSoonToTryAgain($user)) {
            return new LoginAttempt();
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
            return new LoginAttempt();
        }

        if ($new_password != null) {
            if ($this->passwordTooWeak($username, $new_password)) {
                $has_failed = true;
                $message = trim(
                    'New password is too weak. ' .
                    $this->zxcvbn->getPasswordHints($username, $new_password)
                );
                return new LoginAttempt($has_failed, $message);
            }
            $user->pw_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $user->needs_pw_change = false;
        }

        if ($user->needs_pw_change) {
            $has_failed = true;
            $message = 'You must use the new password option to login.';
            return new LoginAttempt($has_failed, $message);
        }

        $user->ongoing_pw_fail_count = 0;
        $this->store->updateUser($user);
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
}
