<?php

namespace madman\Password;

use PHPUnit\Framework\TestCase;

class MySQLStoreTest extends TestCase
{
    const MYSQL_TEST_CONFIG_FILE = __DIR__ . '/config_db.php';
    const MYSQL_REF_SCHEMA_FILE = __DIR__ . '/mysql_build_ref_schema.sql';
    const MYSQL_TEST_DATA_FILE = __DIR__ . '/mysql_load_test_data.sql';

    private static $store;

    public static function setUpBeforeClass()
    {
        self::$store = self::getTestDatastore();
    }

    public static function getTestDatastore($new_link = false)
    {
        $store = new MySQLStore();
        $db_config = require(self::MYSQL_TEST_CONFIG_FILE);
        $store->openConnection(
            $db_config['host'],
            $db_config['username'],
            $db_config['password'],
            $db_config['db_name'],
            $new_link
        );
        self::buildTestDatabaseSchema($store);
        return $store;
    }

    protected static function buildTestDatabaseSchema($store)
    {
        $filename = self::MYSQL_REF_SCHEMA_FILE;
        $fail_message = 'Failed to build MySQL FA reference tables.';
        $store->executeSQLFromFile($filename, $fail_message);
        $store->buildDatabaseSchema();
    }

    protected function setUp()
    {
        self::loadTestData(self::$store);
    }

    public static function loadTestData($store)
    {
        $filename = self::MYSQL_TEST_DATA_FILE;
        $fail_message = 'Failed to load MySQL test data.';
        $store->executeSQLFromFile($filename, $fail_message);
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
        $this->assertEquals('fa23test', $row[0]);
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
        $store = self::getTestDatastore($create_new_link);
        return $store->conn;
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

    public function testDoQueryAndGetRowReturnsRow()
    {
        $sql = 'SELECT 1+1';
        $fail_message = 'Can not add.';
        $row = self::$store->doQueryAndGetRow($sql, $fail_message);
        $this->assertEquals('2', $row[0]);
    }

    public function testDoQueryThrowsOnError()
    {
        $sql = 'SELECT unknown_column';
        $fail_message = 'Did bad select.';

        $exception_msg = (
            "Did bad select. Cause: Unknown column 'unknown_column' in " .
            "'field list'. SQL:\n" .
            "SELECT unknown_column"
        );
        $this->expectExceptionMessage($exception_msg);
        $this->expectExceptionCode(Datastore::QUERY_ERROR);

        self::$store->doQuery($sql, $fail_message);
    }

    public function testDoQueryAndGetRowThrowsWhenNoRow()
    {
        $sql = 'SELECT oid FROM 0_pwe_user WHERE oid = -999';
        $fail_message = 'No matching row to get.';

        $this->expectExceptionCode(Datastore::NO_MATCHING_ROW_FOUND);
        self::$store->doQueryAndGetRow($sql, $fail_message);
    }

    public function testCommitTransactionCommits()
    {
        self::$store->startTransaction();
        $this->deleteExtUsers();
        self::$store->commitTransaction();
        $user_count = $this->getExtUsersCount();
        $this->assertEquals(0, $user_count, 'did not commit transaction');
    }

    private function deleteExtUsers()
    {
        $sql = "DELETE FROM 0_pwe_user";
        $fail_message = "Could not delete rows.";
        self::$store->doQuery($sql, $fail_message);
    }

    private function getExtUsersCount()
    {
        $sql = "SELECT count(*) FROM 0_pwe_user";
        $fail_message = "Could not get row count.";
        $row = self::$store->doQueryAndGetRow($sql, $fail_message);
        return $row[0];
    }

    public function testRollbackTransactionRollsback()
    {
        $before_count = $this->getExtUsersCount();
        self::$store->startTransaction();
        $this->deleteExtUsers();
        self::$store->rollbackTransaction();
        $after_count = $this->getExtUsersCount();
        $this->assertEquals(
            $before_count,
            $after_count,
            'did not rollback transaction'
        );
    }

    public function testBuildSchemaCreatesTables()
    {
        self::$store->buildDatabaseSchema();

        $sql = "SELECT count(*)
                FROM information_schema.tables
                WHERE table_schema = schema()
                    AND table_name IN ('0_pwe_user', '0_pwe_config');
        ";
        $fail_message = "Could not get count of matching tables.";
        $row = self::$store->doQueryAndGetRow($sql, $fail_message);
        $this->assertEquals(
            2,
            $row[0],
            'database missing expected tables'
        );
    }

    public function testGetConfigReturnsConfig()
    {
        $config = self::$store->getConfig();
        $this->assertTrue($config instanceof Config);
        $this->assertEquals(
            $config::DEFAULT_LOGIN_FAIL_THRESHOLD_COUNT,
            $config->login_fail_threshold_count
        );
        $this->assertEquals(
            $config::DEFAULT_LOGIN_FAIL_LOCK_MINUTES,
            $config->login_fail_lock_minutes
        );
    }

    public function testGetUserReturnsUser()
    {
        $user = self::$store->getUserByUsername('dscully');
        $this->assertTrue($user instanceof User);
        $this->assertEquals(102, $user->oid);
        $this->assertEquals('dscully', $user->username);
        $this->assertEquals(
            '$2y$10$vra/wVFQUZHlOaVYIqPew.SbYCmTJDdKmOXHPdq038d6z08xSe.4G',
            $user->pw_hash
        );
        $this->assertEquals(true, $user->needs_pw_change);
        $this->assertEquals(true, $user->is_locked);
        $this->assertEquals(99, $user->ongoing_pw_fail_count);
        $nye_2020 = self::$store->convertToPHPDate('2020-01-01 00:00:00');
        $this->assertEquals($nye_2020, $user->last_pw_fail_time);
    }

    public function testGetUserThrowsOnUnknown()
    {
        $this->expectExceptionCode(Datastore::NO_MATCHING_ROW_FOUND);
        $this->expectExceptionMessage(
            'Could not get user (username=UNKNOWN).'
        );
        self::$store->getUserByUsername('UNKNOWN');
    }

    public function testUpdateUserUpdatesUser()
    {
        $user = self::$store->getUserByUsername('fmulder');
        $user->pw_hash = 'different';
        $user->needs_pw_change = true;
        $user->is_locked = true;
        $user->ongoing_pw_fail_count = 16;
        $update_time = date_create('now');
        $user->last_pw_fail_time = $update_time;

        self::$store->updateUser($user);

        $user_after = self::$store->getUserByUsername('fmulder');
        $this->assertEquals($user, $user_after);
    }
}
