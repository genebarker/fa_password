<?php

namespace madman\Password;

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testHasExpectedAttributes()
    {
        $config = new Config();
        $attribute = $this->getAttributeNames();
        foreach ($attribute as $attr) {
            $this->assertTrue(
                property_exists($config, $attr),
                "missing expected attribute: $attr"
            );
        }
    }

    public function getAttributeNames()
    {
        return [
            'login_fail_threshold_count',
            'login_fail_lock_minutes',
            'minimum_password_strength',
            'maximum_password_age_days',
            'password_history_count',
        ];
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

    public function testValidateSucceedsOnGoodValues()
    {
        $config = new Config();
        $this->assertTrue($config->hasValidValues());
    }

    public function testValidateFailsOnBadValues()
    {
        $attribute = $this->getAttributeNames();
        foreach ($attribute as $attr) {
            $bad_value = [null, '', 'a', -1];
            foreach ($bad_value as $value) {
                $config = new Config();
                $config->$attr = $value;
                $this->assertFalse(
                    $config->hasValidValues(),
                    "failed to detect bad value: $value " .
                    "for attribute: $attr\n"
                );
            }
        }
    }

    public function testCantExceedMaximumPossibleStrength()
    {
        $config = new Config();
        $config->minimum_password_strength = 5;
        $this->assertFalse(
            $config->hasValidValues(),
            'minimum_password_strength can not be greater than 4' 
        );
    }

    public function testAttributesCanBeZero()
    {
        $attribute = $this->getAttributeNames();
        foreach ($attribute as $attr) {
            $config = new Config();
            $config->$attr = 0;
            $this->assertTrue(
                $config->hasValidValues(),
                "failed to allow zero value for attribute: $attr\n"
            );
        }
    }
}
