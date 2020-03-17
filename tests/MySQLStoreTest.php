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
        $store->addExtensionTables();
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

    public function testDoQueryHandlesDefaultFACompanyPrefix()
    {
        $sql = "SELECT * FROM 0_pwe_user";
        self::$store->doQuery($sql);
        $this->assertEquals($sql, self::$store->last_query);
    }

    public function testDoQueryHandlesAlternateFACompanyPrefix()
    {
        $store = new MySQLStore(21);
        $store->setConnection(self::$store->conn);
        $sql = "SELECT ' 0_pwe_user'";
        $store->doQuery($sql);
        $this->assertEquals("SELECT ' 21_pwe_user'", $store->last_query);
    }

    public function testDoQueryAndGetRowThrowsWhenNoRow()
    {
        $sql = 'SELECT oid FROM 0_pwe_user WHERE oid = -999';
        $fail_message = 'No matching row to get.';

        $this->expectExceptionCode(Datastore::NO_MATCHING_ROW_FOUND);
        self::$store->doQueryAndGetRow($sql, $fail_message);
    }

    public function testUpdateQueryThrowsWhenNoRowsMatched()
    {
        $sql = 'UPDATE 0_pwe_user SET pw_hash = null WHERE oid = -999';
        $fail_message = 'No matching rows to update.';

        $this->expectExceptionCode(Datastore::NO_MATCHING_ROW_FOUND);
        self::$store->doUpdateQuery($sql, $fail_message);
    }

    public function testUpdateQueryReturnsRowsMatched()
    {
        $sql = "UPDATE 0_pwe_user SET pw_hash = null
                WHERE oid IN (101, 102)
        ";
        $fail_message = "Can not update users.";

        $rows_matched = self::$store->doUpdateQuery($sql, $fail_message);
        $this->assertEquals(2, $rows_matched, 'Should match 2 users.');
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

    public function testAddExtensionTablesAddsThem()
    {
        self::$store->addExtensionTables();

        $this->assertEquals(
            3,
            $this->getCountOfExtensionTables(),
            'database missing expected tables'
        );
    }

    private function getCountOfExtensionTables()
    {
        $sql = "SELECT count(*)
                FROM information_schema.tables
                WHERE table_schema = schema()
                    AND table_name IN ('0_pwe_user', '0_pwe_config',
                        '0_pwe_history')
        ";
        $fail_message = "Could not get count of matching tables.";
        $row = self::$store->doQueryAndGetRow($sql, $fail_message);
        return $row[0];
    }

    public function testRemoveExtensionTablesRemovesThem()
    {
        self::$store->removeExtensionTables();
        $table_count = $this->getCountOfExtensionTables();
        self::$store->addExtensionTables(); // put them back!
        $this->assertEquals(
            0,
            $table_count,
            'database still has some extension tables'
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
        $this->assertEquals(
            $config::DEFAULT_MINIMUM_PASSWORD_STRENGTH,
            $config->minimum_password_strength
        );
        $this->assertEquals(
            $config::DEFAULT_MAXIMUM_PASSWORD_AGE_DAYS,
            $config->maximum_password_age_days
        );
    }

    public function testUpdateConfigUpdatesIt()
    {
        $config = new Config();
        $config->login_fail_threshold_count = 2;
        $config->login_fail_lock_minutes = 3;
        $config->minimum_password_strength = 4;
        $config->maximum_password_age_days = 5;
        $config->password_history_count = 6;
        self::$store->updateConfig($config);
        $config2 = self::$store->getConfig();
        $this->assertEquals($config, $config2);
    }

    public function testGetUserReturnsUser()
    {
        $user = self::$store->getUserByUsername('dscully');
        $this->assertTrue($user instanceof User);
        $this->assertEquals(102, $user->oid);
        $this->assertEquals('dscully', $user->username);
        $this->assertEquals(
            '71720c4911b0c34c25ed4b3aa188bdb8',
            $user->fa_pw_hash
        );
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

    public function testGetBaseUserReturnsUser()
    {
        $user = self::$store->getBaseUserByUsername('skinner');
        $this->assertTrue($user instanceof User);
        $this->assertEquals(103, $user->oid);
        $this->assertEquals('skinner', $user->username);
        $this->assertEquals(
            '5cc224100427d254d62b1fe5fc7883b3',
            $user->fa_pw_hash
        );
    }

    public function testUpdateUserUpdatesUser()
    {
        $user = self::$store->getUserByUsername('fmulder');
        $user->fa_pw_hash = 'different';
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

    public function testUpdateUserHandlesNullDates()
    {
        $user_before = self::$store->getUserByUsername('fmulder');
        $user_before->last_pw_fail_time = null;
        self::$store->updateUser($user_before);
        $user_after = self::$store->getUserByUsername('fmulder');
        $this->assertEquals($user_before, $user_after);
    }

    public function testUpdateUserThrowsOnError()
    {
        $user = self::$store->getUserByUsername('fmulder');
        $user->oid = -999;

        $this->expectExceptionCode(Datastore::NO_MATCHING_ROW_FOUND);
        $this->expectExceptionMessage(
            'Could not update user (oid=-999, username=fmulder).'
        );
        self::$store->updateUser($user);
    }

    public function testInsertUserInsertsHim()
    {
        $user_before = UserTest::createTestUser(104, 'doggett');
        self::$store->insertUser($user_before);
        $user_after = self::$store->getUserByUsername('doggett');
        $this->assertEquals($user_before, $user_after);
    }

    public function testGetPasswordHistoryGetsIt()
    {
        $history = self::$store->getPasswordHistory(101);
        $pw_3 = $history[2];
        $this->assertTrue(password_verify('scully', $pw_3['pw_hash']));
        $this->assertEquals(
            gmdate('Y-m-d'), // test data inserted with GMT date of now
            date_format($pw_3['dob'], 'Y-m-d')
        );
    }
}
