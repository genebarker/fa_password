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

    public function testGetPasswordHintsReturnNoneWhenNone()
    {
        $zxcvbn = new ZxcvbnWrapper();
        $hints = $zxcvbn->getPasswordHints('fmulder', 'sole was here');
        $this->assertEquals('Password appears strong.', $hints);
    }

    public function testGetPasswordHintsReturnsSuggestion()
    {
        $zxcvbn = new ZxcvbnWrapper();
        $hints = $zxcvbn->getPasswordHints('fmulder', 'one two');
        $this->assertEquals(
            'Add another word or two. Uncommon words are better.',
            $hints
        );
    }

    public function testGetPasswordHintsPrependsWarningToSuggestion()
    {
        $zxcvbn = new ZxcvbnWrapper();
        $hints = $zxcvbn->getPasswordHints('fmulder', 'password');
        $this->assertEquals(
            'This is a top-10 common password. Add another word or two. ' .
            'Uncommon words are better.',
            $hints
        );
    }
}
