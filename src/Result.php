<?php

namespace madman\Password;

class Result
{
    public $has_failed;
    public $message;

    public function __construct(
        $has_failed = true,
        $message = 'Request failed.'
    ) {
        $this->has_failed = $has_failed;
        $this->message = $message;
    }
}
