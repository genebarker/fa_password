<?php

namespace madman\Password;

class LoginAttempt
{
    public $has_failed;
    public $message;

    public function __construct(
        $has_failed = true,
        $message = 'Login attempt failed.'
    ) {
        $this->has_failed = $has_failed;
        $this->message = $message;
    }
}
