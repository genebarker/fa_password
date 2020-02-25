<?php

namespace madman\Password;

use PHPUnit\Framework\TestCase;

class MySQLStoreTest extends TestCase
{
    private static $db_config;
    private static $store;

    public static function setUpBeforeClass()
    {
        self::$db_config = require('config_db.php');
        self::$store = self::getDatastore();
        self::$store->buildDatabaseSchema();
    }

    private static function getDatastore()
    {
        $store = new MySQLStore();
        $store->openConnection(
            self::$db_config['host'],
            self::$db_config['username'],
            self::$db_config['password'],
            self::$db_config['db_name']
        );
        return $store;
    }

    protected function setUp()
    {
        self::$store->executeSQLFromFile('mysql_load_test_data.sql');
    }

    public function testImplementsDatastore()
    {
        $store = new MySQLStore();
        $this->assertTrue($store instanceof Datastore);
    }

    public function testOpenConnectionOpens()
    {
        $conn = self::$store->conn;

        $sql = 'SELECT DATABASE();';
        $result = mysql_query($sql, $conn);
        $row = mysql_fetch_row($result);
        $this->assertEquals(
            self::$db_config['db_name'],
            $row[0]
        );
    }

    public function testCloseConnectionCloses()
    {
        $conn = $this->getPrivateLinkToDatabase();
        $store = new MySQLStore();
        $store->setConnection($conn);
        $store->closeConnection();

        $this->assertFalse(
            is_resource($conn)
            && get_resource_type($conn) === 'mysql link'
        );
    }

    private function getPrivateLinkToDatabase()
    {
        $create_new_link = true;
        $link = mysql_connect(
            self::$db_config['host'],
            self::$db_config['username'],
            self::$db_config['password'],
            $create_new_link
        );
        return $link;
    }

    public function testSetConnectionSets()
    {
        $conn = $this->getPrivateLinkToDatabase();
        $store = new MySQLStore();
        $store->setConnection($conn);
        $this->assertEquals($conn, $store->getConnection());
    }

    public function testGetVersionReturnsDBVersion()
    {
        $version = self::$store->getVersion();
        $this->assertRegExp('/MySQL \d+\.\d+.*/', $version);
    }

    public function testBuildSchemaCreatesTables()
    {
        self::$store->buildDatabaseSchema();

        $conn = self::$store->conn;
        $sql = "SELECT count(*)
                FROM information_schema.tables
                WHERE table_schema = schema()
                    AND table_name IN ('0_pwe_user');
        ";
        $result = mysql_query($sql, $conn);
        $row = mysql_fetch_row($result);
        $this->assertEquals(
            1,
            $row[0],
            'database missing expected tables'
        );
    }

    public function testGetUserReturnsUser()
    {
        $user = self::$store->getUserByUsername('fmulder');
        $this->assertTrue($user instanceof User);
        $this->assertEquals(101, $user->oid);
        $this->assertEquals('fmulder', $user->username);
        $this->assertEquals(
            '$2y$10$5BEkSCYW3k//CaCIejTJNu7uHiGcyFHF9N9oDHCls7/qFSugv5GZu',
            $user->pw_hash
        );
    }
}
