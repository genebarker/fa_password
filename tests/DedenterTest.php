<?php

namespace madman\Password;

use PHPUnit\Framework\TestCase;

class DendenterTest extends TestCase
{
    public function testReturnsSingleLineUnchanged()
    {
        $example = '  Small line.  ';
        $trimmed = Dedenter::dedent($example);
        $this->assertEquals('  Small line.  ', $trimmed);
    }

    public function testReturnsSingleLineWithLineEndingUnchanged()
    {
        $this->assertEquals("a\n", Dedenter::dedent("a\n"));
    }

    public function testTrimsBlockMargin()
    {
        $example = 'line 1
                    line 2
                    line 3
        ';
        $trimmed = Dedenter::dedent($example);
        $expected = "line 1\n" .
                    "line 2\n" .
                    "line 3\n";
        $this->assertEquals($expected, $trimmed);
    }

    public function testTrimsSmallestMargin()
    {
        $example = 'line 1
                        line 2
                    line 3
        ';
        $trimmed = Dedenter::dedent($example);
        $expected = "line 1\n" .
                    "    line 2\n" .
                    "line 3\n";
        $this->assertEquals($expected, $trimmed);
    }
}
