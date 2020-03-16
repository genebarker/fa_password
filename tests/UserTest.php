<?php

namespace madman\Password;

use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    const NOON_XMAS_2019 = '2019-12-25 12:00:00';

    public function testConstructsAsExpected()
    {
        $oid = 42;
        $username = 'hitchiker';
        $user = new User($oid, $username);
        $this->assertEquals($oid, $user->oid);
        $this->assertEquals($username, $user->username);
        $this->assertEquals(null, $user->fa_pw_hash);
        $this->assertEquals(null, $user->pw_hash);
        $this->assertEquals(false, $user->needs_pw_change);
        $this->assertEquals(false, $user->is_locked);
        $this->assertEquals(0, $user->ongoing_pw_fail_count);
        $this->assertEquals(null, $user->last_pw_fail_time);
    }

    public function testEqualsTrueWhenSame()
    {
        $user_one = $this->createTestUser();
        $user_two = $this->createTestUser();
        $this->assertEquals($user_one, $user_two);
    }

    public static function createTestUser()
    {
        $oid = 26;
        $username = 'kobe';
        // password = 'bryant'
        $fa_pw_hash = 'e5b79bbdb797144a4cbf576aef22f492';
        $pw_hash = (
            '$2y$10$dxUH1PYc7dQpjBf5l2jfxO9Ce5M97m11ZIpP5IsjvfhdWBcPhWg/u'
        );
        $user = new User($oid, $username);
        $user->pw_hash = $pw_hash;
        $user->needs_pw_change = true;
        $user->is_locked = true;
        $user->ongoing_pw_fail_count = 20;
        $user->last_pw_fail_time = MySQLStore::convertToPHPDate(
            self::NOON_XMAS_2019
        );
        return $user;
    }

    public function testEqualsFalseWhenAnAttrDifferent()
    {
        $user_one = $this->createTestUser();
        $user_two = $this->createTestUser();
        $user_two->is_locked = false;
        $this->assertNotEquals($user_one, $user_two);
    }
}
