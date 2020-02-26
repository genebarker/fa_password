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
            date_create_from_format('Y-m-d H:i:s', '2019-12-25 12:15:00'),
            $user->last_pw_fail_time
        );
    }
}
