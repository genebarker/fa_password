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
}
