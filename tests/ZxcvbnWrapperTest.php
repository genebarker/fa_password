<?php

namespace madman\Password;

use PHPUnit\Framework\TestCase;

class ZxcvbnWrapperTest extends TestCase
{
    public function testGetPasswordScoreGetsIt()
    {
        $zxcvbn = new ZxcvbnWrapper();
        $score = $zxcvbn->getPasswordScore('fmulder', 'password');
        $this->assertEquals(0, $score);
        $score = $zxcvbn->getPasswordScore('fmulder', 'sole was here');
        $this->assertEquals(4, $score);
    }
}
