<?php

namespace madman\Password;

class User
{
    public $ongoing_pw_fail_count = 0;
    public $last_pw_fail_time = null;

    public function __construct($oid, $username)
    {
        $this->oid = $oid;
        $this->username = $username;
    }
}
