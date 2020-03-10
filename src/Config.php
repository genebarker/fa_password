<?php

namespace madman\Password;

class Config
{
    const DEFAULT_LOGIN_FAIL_THRESHOLD_COUNT = 3;
    const DEFAULT_LOGIN_FAIL_LOCK_MINUTES = 15;
    const DEFAULT_MINIMUM_PASSWORD_STRENGTH = 3;

    public $login_fail_threshold_count;
    public $login_fail_lock_minutes;
    public $minimum_password_strength;
}
