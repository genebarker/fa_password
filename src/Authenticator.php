<?php

namespace madman\Password;

use DateInterval;

class Authenticator
{
    public $store;
    public $config;

    public function __construct($store)
    {
        $this->store = $store;
        $this->config = $store->getConfig();
    }

    public function login($username, $password)
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

        $user->ongoing_pw_fail_count = 0;
        $this->store->updateUser($user);
        $has_failed = false;
        $message = "Welcome back $username.";
        return new LoginAttempt($has_failed);
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
}
