<?php

use PHPUnit\Framework\TestCase;

include __DIR__ . '/../Datastore.php';

class DatastoreTest extends TestCase
{
    function testIsInterface()
    {
        $reflection = new \ReflectionClass('Datastore');
        $this->assertTrue($reflection->isInterface());
    }

    function testHasExpectedMethods()
    {
        $method = array(
            'openConnection',
            'getConnection',
            'setConnection',
            'closeConnection',
            'getVersion',
        );
        
        $reflection = new \ReflectionClass('Datastore');
        foreach ($method as $method_name) {
            $this->assertTrue(
                $reflection->hasMethod($method_name),
                'missing interface method: ' . $method_name);
        }
    }

}

?>
