<?php

namespace madman\Password;

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
        self::$store->executeSQLFromFile('mysql_load_test_data.sql');
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
    }

    public function triggerLockForUser($username)
    {
        $lock_threshold = Authenticator::LOGIN_FAIL_THRESHOLD_COUNT;
        for ($i = 1; $i <= $lock_threshold; $i++) {
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
}
