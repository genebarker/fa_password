<?php

namespace madman\Password;

class Authenticator
{
    const LOGIN_FAIL_THRESHOLD_COUNT = 3;
    public $store;

    public function __construct($store)
    {
        $this->store = $store;
    }

    public function login($username, $password)
    {
        try {
            $user = $this->store->getUserByUsername($username);
        } catch (\Exception $e) {
            return new LoginAttempt();
        }
        if (!password_verify($password, $user->pw_hash)) {
            $user->ongoing_pw_fail_count++;
            $user->last_pw_fail_time = date_create('now');
            if (
                $user->ongoing_pw_fail_count
                > self::LOGIN_FAIL_THRESHOLD_COUNT
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
}
