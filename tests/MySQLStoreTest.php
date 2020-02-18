<?php

use PHPUnit\Framework\TestCase;

include_once __DIR__ . '/../Datastore.php';
include_once __DIR__ . '/../MySQLStore.php';

class MySQLStoreTest extends TestCase
{
    function testImplementsDatastore()
    {
        $store = new MySQLStore();
        $this->assertTrue($store instanceof Datastore);
    }
}

?>
