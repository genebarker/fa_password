<?php

namespace madman\Password;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Datastore.php';
require_once __DIR__ . '/../MySQLStore.php';

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

    public function testOpenConnectionOpensOne()
    {
        $store = new MySQLStore();
        $link = $store->openConnection(
            $this->db_config['host'],
            $this->db_config['username'],
            $this->db_config['password'],
            $this->db_config['db_name']
        );

        $sql = 'SELECT DATABASE();';
        $result = mysql_query($sql, $link);
        $row = mysql_fetch_row($result);
        $this->assertEquals(
            $this->db_config['db_name'],
            $row[0]
        );
    }
}
