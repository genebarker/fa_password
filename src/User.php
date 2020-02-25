<?php

namespace madman\Password;

class User
{
    public function __construct($oid, $username)
    {
        $this->oid = $oid;
        $this->username = $username;
    }
}
