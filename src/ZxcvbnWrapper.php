<?php

namespace madman\Password;

use ZxcvbnPhp\Zxcvbn;

class ZxcvbnWrapper
{
    public $zxcvbn;

    public function __construct()
    {
        $this->zxcvbn = new Zxcvbn();
    }

    public function getPasswordScore($username, $password)
    {
        $user_data = [
            $username,
        ];
        $strength = $this->zxcvbn->passwordStrength($password, $user_data);
        return $strength['score'];
    }
}
