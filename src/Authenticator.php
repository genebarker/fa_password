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
        return new LoginAttempt();
    }
}
