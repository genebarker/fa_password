<?php

namespace madman\Password;

class Config
{
    const DEFAULT_LOGIN_FAIL_THRESHOLD_COUNT = 3;
    const DEFAULT_LOGIN_FAIL_LOCK_MINUTES = 15;

    public $login_fail_threshold_count;
    public $login_fail_lock_minutes;
}
