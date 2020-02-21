<?php

namespace madman\Password;

use PHPUnit\Framework\TestCase;

class DatastoreTest extends TestCase
{
    public function testIsInterface()
    {
        $reflection = new \ReflectionClass('madman\Password\Datastore');
        $this->assertTrue($reflection->isInterface());
    }

    public function testHasExpectedMethods()
    {
        $method = array(
            'openConnection',
            'getConnection',
            'setConnection',
            'closeConnection',
            'getVersion',
        );

        $reflection = new \ReflectionClass('madman\Password\Datastore');
        foreach ($method as $method_name) {
            $this->assertTrue(
                $reflection->hasMethod($method_name),
                'missing interface method: ' . $method_name
            );
        }
    }
}
