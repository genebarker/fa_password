<?php

namespace madman\Password;

use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testConstructsAsExpected()
    {
        $oid = 42;
        $username = 'hitchiker';
        $user = new User($oid, $username);
        $this->assertEquals($oid, $user->oid);
        $this->assertEquals($username, $user->username);
        $this->assertEquals(null, $user->pw_hash);
        $this->assertEquals(false, $user->needs_pw_change);
        $this->assertEquals(false, $user->is_locked);
        $this->assertEquals(0, $user->ongoing_pw_fail_count);
        $this->assertEquals(null, $user->last_pw_fail_time);
    }
}
