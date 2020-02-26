<?php

namespace madman\Password;

class Authenticator
{
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
            return new LoginAttempt();
        }

        $user->ongoing_pw_fail_count = 0;
        $sql = "UPDATE 0_pwe_user
                SET ongoing_pw_fail_count = 0
                WHERE oid = $user->oid";
        mysql_query($sql, $this->store->conn);
        $has_failed = false;
        $message = "Welcome back $username.";
        return new LoginAttempt($has_failed);
    }
}
