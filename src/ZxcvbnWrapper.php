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
        $strength = $this->getPasswordStrength($username, $password);
        return $strength['score'];
    }

    public function getPasswordStrength($username, $password)
    {
        $user_data = [
            $username,
        ];
        return $this->zxcvbn->passwordStrength($password, $user_data);
    }

    public function getPasswordHints($username, $password)
    {
        $strength = $this->getPasswordStrength($username, $password);
        $feedback = $strength['feedback'];
        $warning = $feedback['warning'];
        $suggestions = $feedback['suggestions'];

        if ($warning == '' && count($suggestions) == 0) {
            return 'Password appears strong.';
        }

        $hints = ($warning == '' ? '' : $warning . '. ');
        if (count($suggestions) > 0) {
            $hints .= $suggestions[0];
        }
        return trim($hints);
    }
}
