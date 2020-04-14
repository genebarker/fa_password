<?php

namespace madman\Password;

class Config
{
    const DEFAULT_LOGIN_FAIL_THRESHOLD_COUNT = 3;
    const DEFAULT_LOGIN_FAIL_LOCK_MINUTES = 15;
    const DEFAULT_MINIMUM_PASSWORD_STRENGTH = 2;
    const DEFAULT_MAXIMUM_PASSWORD_AGE_DAYS = 90;
    const DEFAULT_PASSWORD_HISTORY_COUNT = 10;

    public $login_fail_threshold_count;
    public $login_fail_lock_minutes;
    public $minimum_password_strength;
    public $maximum_password_age_days;
    public $password_history_count;

    public function __construct()
    {
        $this->login_fail_threshold_count = self::DEFAULT_LOGIN_FAIL_THRESHOLD_COUNT;
        $this->login_fail_lock_minutes = self::DEFAULT_LOGIN_FAIL_LOCK_MINUTES;
        $this->minimum_password_strength = self::DEFAULT_MINIMUM_PASSWORD_STRENGTH;
        $this->maximum_password_age_days = self::DEFAULT_MAXIMUM_PASSWORD_AGE_DAYS;
        $this->password_history_count = self::DEFAULT_PASSWORD_HISTORY_COUNT;
    }

    public function hasValidValues()
    {
        $attribute = [
            'login_fail_threshold_count',
            'login_fail_lock_minutes',
            'minimum_password_strength',
            'maximum_password_age_days',
            'password_history_count',
        ];
        foreach ($attribute as $attr) {
            $value = $this->$attr;
            if (is_null($value) || !is_int($value) || $value < 0) {
                return false;
            }
        }

        if ($this->minimum_password_strength > 4) {
            return false;
        }

        return true;
    }
}
