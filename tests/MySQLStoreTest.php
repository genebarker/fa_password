<?php

namespace madman\Password;

use PHPUnit\Framework\TestCase;

class MySQLStoreTest extends TestCase
{
    protected function setUp()
    {
        $this->db_config = require("config_db.php");
    }

    public function testImplementsDatastore()
    {
        $store = new MySQLStore();
        $this->assertTrue($store instanceof Datastore);
    }

    public function testOpenConnectionOpens()
    {
        $store = $this->getDatastore();
        $conn = $store->conn;

        $sql = 'SELECT DATABASE();';
        $result = mysql_query($sql, $conn);
        $row = mysql_fetch_row($result);
        $this->assertEquals(
            $this->db_config['db_name'],
            $row[0]
        );
    }

    private function getDatastore()
    {
        $store = new MySQLStore();
        $store->openConnection(
            $this->db_config['host'],
            $this->db_config['username'],
            $this->db_config['password'],
            $this->db_config['db_name']
        );
        return $store;
    }

    public function testCloseConnectionCloses()
    {
        $store = $this->getDatastore();
        $conn = $store->conn;
        $store->closeConnection();

        $this->assertFalse(
            is_resource($conn)
            && get_resource_type($conn) === 'mysql link'
        );
    }

    public function testSetConnectionSets()
    {
        $link = mysql_connect(
            $this->db_config['host'],
            $this->db_config['username'],
            $this->db_config['password'],
            $this->db_config['db_name']
        );

        $store = new MySQLStore();
        $store->setConnection($link);
        $this->assertEquals($link, $store->getConnection());
    }

    public function testGetVersionReturnsDBVersion()
    {
        $store = $this->getDatastore();
        $version = $store->getVersion();
        $this->assertRegExp('/MySQL \d+\.\d+.*/', $version);
    }
}
