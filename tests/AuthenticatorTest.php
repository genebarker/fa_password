<?php

namespace madman\Password;

use DateInterval;
use PHPUnit\Framework\TestCase;

class AuthenticatorTest extends TestCase
{
    private static $store;
    private static $authenticator;

    public static function setUpBeforeClass()
    {
        $db_config = require('config_db.php');
        $store = new MySQLStore();
        $store->openConnection(
            $db_config['host'],
            $db_config['username'],
            $db_config['password'],
            $db_config['db_name']
        );
        $store->buildDatabaseSchema();
        self::$store = $store;
        self::$authenticator = new Authenticator($store);
    }

    public static function tearDownAfterClass()
    {
        self::$store->closeConnection();
    }

    protected function setUp()
    {
        $filename = MySQLStoreTest::MYSQL_TEST_DATA_FILE;
        $fail_message = 'Failed to load MySQL test data.';
        self::$store->executeSQLFromFile($filename, $fail_message);
    }

    protected function tearDown()
    {
    }

    public function testUnknownUserFails()
    {
        $loginAttempt = self::$authenticator->login(
            'mrunknown',
            'some_password'
        );
        $this->assertTrue($loginAttempt->has_failed);
    }

    public function testGoodUserSucceeds()
    {
        $loginAttempt = self::$authenticator->login('fmulder', 'scully');
        $this->assertFalse($loginAttempt->has_failed);
    }

    public function testGoodUserResetsPasswordFailCount()
    {
        $loginAttempt = self::$authenticator->login('fmulder', 'scully');
        $user = self::$store->getUserByUsername('fmulder');
        $this->assertEquals(0, $user->ongoing_pw_fail_count);
        $this->assertEquals(
            self::$store->convertToPHPDate('2019-12-25 12:15:00'),
            $user->last_pw_fail_time
        );
    }

    public function testGoodUserBadPassFails()
    {
        $loginAttempt = self::$authenticator->login('fmulder', 'wrong_pw');
        $this->assertEquals(true, $loginAttempt->has_failed);
        $this->assertEquals(
            'Login attempt failed.',
            $loginAttempt->message
        );
    }

    public function testGoodUserBadPassUpdatesFailFields()
    {
        $user_before = self::$store->getUserByUsername('fmulder');
        $login_time = date_create('now');
        $loginAttempt = self::$authenticator->login('fmulder', 'wrong_pw');
        $user_after = self::$store->getUserByUsername('fmulder');

        $this->assertEquals(
            $user_before->ongoing_pw_fail_count + 1,
            $user_after->ongoing_pw_fail_count
        );
        $this->assertTrue($user_after->last_pw_fail_time >= $login_time);
    }

    public function testUserLocksAfterTooManyBadPasswords()
    {
        $this->triggerLockForUser('fmulder');
        $user = self::$store->getUserByUsername('fmulder');
        $this->assertEquals(true, $user->is_locked);
        $this->assertEquals(
            self::$authenticator->config->login_fail_threshold_count + 1,
            $user->ongoing_pw_fail_count
        );
    }

    public function triggerLockForUser($username)
    {
        $user = self::$store->getUserByUsername($username);
        $times_to_fail = (
            self::$authenticator->config->login_fail_threshold_count
            + 1
            - $user->ongoing_pw_fail_count
        );
        for ($i = 1; $i <= $times_to_fail; $i++) {
            $loginAttempt = self::$authenticator->login(
                $username,
                'the_wrong_password'
            );
        }
    }

    public function testLoginFailsWhenUserLocked()
    {
        $this->triggerLockForUser('fmulder');
        $loginAttempt = self::$authenticator->login('fmulder', 'scully');
        $this->assertEquals(true, $loginAttempt->has_failed);
    }

    public function testLockResetsAfterSetTime()
    {
        $this->triggerLockForUser('fmulder');
        $minutes = self::$authenticator->config->login_fail_lock_minutes;
        $lock_duration = new DateInterval("PT{$minutes}M");
        $fail_time = date_sub(date_create('now'), $lock_duration);
        $user = self::$store->getUserByUsername('fmulder');
        $user->last_pw_fail_time = $fail_time;
        self::$store->updateUser($user);
        $loginAttempt = self::$authenticator->login('fmulder', 'scully');
        $this->assertEquals(false, $loginAttempt->has_failed);
    }
}
