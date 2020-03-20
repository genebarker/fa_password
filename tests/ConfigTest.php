<?php

namespace madman\Password;

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testHasExpectedAttributes()
    {
        $config = new Config();
        $attribute = [
            'login_fail_threshold_count',
            'login_fail_lock_minutes',
            'minimum_password_strength',
            'maximum_password_age_days',
            'password_history_count',
        ];
        foreach ($attribute as $attr) {
            $this->assertTrue(
                property_exists($config, $attr),
                "missing expected attribute: $attr"
            );
        }
    }

    public function testInitializationSetsToDefaultValues()
    {
        $config = new Config();
        $this->assertEquals(
            Config::DEFAULT_LOGIN_FAIL_THRESHOLD_COUNT,
            $config->login_fail_threshold_count
        );
        $this->assertEquals(
            Config::DEFAULT_LOGIN_FAIL_LOCK_MINUTES,
            $config->login_fail_lock_minutes
        );
        $this->assertEquals(
            Config::DEFAULT_MINIMUM_PASSWORD_STRENGTH,
            $config->minimum_password_strength
        );
        $this->assertEquals(
            Config::DEFAULT_MAXIMUM_PASSWORD_AGE_DAYS,
            $config->maximum_password_age_days
        );
        $this->assertEquals(
            Config::DEFAULT_PASSWORD_HISTORY_COUNT,
            $config->password_history_count
        );
    }
}
