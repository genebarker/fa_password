<?php

namespace madman\Password;

class User
{
    public function __construct($oid, $username)
    {
        $this->oid = $oid;
        $this->username = $username;
        $this->fa_pw_hash = null;

        $this->pw_hash = null;
        $this->needs_pw_change = false;
        $this->is_locked = false;
        $this->ongoing_pw_fail_count = 0;
        $this->last_pw_fail_time = null;
    }
}
