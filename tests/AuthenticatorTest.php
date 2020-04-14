<?php

namespace madman\Password;

use DateInterval;
use DateTime;
use PHPUnit\Framework\TestCase;

class AuthenticatorTest extends TestCase
{
    const GOOD_NEW_PASSWORD = 'someNEWpassword!';

    private static $store;
    private static $authenticator;

    public static function setUpBeforeClass()
    {
        self::$store = MySQLStoreTest::getTestDatastore();
        self::$authenticator = new Authenticator(self::$store);
    }

    public static function tearDownAfterClass()
    {
        self::$store->closeConnection();
    }

    protected function setUp()
    {
        MySQLStoreTest::loadTestData(self::$store);
    }

    protected function tearDown()
    {
    }

    public function testUnknownUserFails()
    {
        $result = self::$authenticator->login(
            'mrunknown',
            'some_password'
        );
        $this->assertTrue($result->has_failed);
        $this->assertEquals(
            'This account does not exist. Please enter a different ' .
            'username or contact your system administrator.',
            $result->message
        );
    }

    public function testGoodUserSucceeds()
    {
        $result = self::$authenticator->login('fmulder', 'scully');
        $this->assertFalse($result->has_failed);
    }

    public function testGoodUserResetsPasswordFailCount()
    {
        $result = self::$authenticator->login('fmulder', 'scully');
        $user = self::$store->getUserByUsername('fmulder');
        $this->assertEquals(0, $user->ongoing_pw_fail_count);
        $this->assertEquals(
            self::$store->convertToPHPDate('2019-12-25 12:15:00'),
            $user->last_pw_fail_time
        );
    }

    public function testLoginUnexpectedExpectionResult()
    {
        $method_name = 'login';
        $this->assertHandlesUnexpectedException($method_name);
    }

    public function assertHandlesUnexpectedException($method_name)
    {
        $auth = new Authenticator(self::$store);
        $auth->store = null;
        $result = $auth->$method_name('nobody', 'some_pw');
        $this->assertTrue($result->has_failed);
        $expected = 'An unexpected error occurred while processing your ' .
                    'request. Username: nobody. Cause: call_user_func() ' .
                    'expects parameter 1 to be a valid callback,';
        $this->assertStringStartsWith($expected, $result->message);
    }

    public function testGoodUserBadPassFails()
    {
        $result = self::$authenticator->login('fmulder', 'wrong_pw');
        $this->assertBadPasswordResult($result);
    }

    private function assertBadPasswordResult($result)
    {
        $this->assertEquals(true, $result->has_failed);
        $this->assertEquals(
            'The password for this account is incorrect.',
            $result->message
        );
    }

    public function testGoodUserBadPassUpdatesFailFields()
    {
        $user_before = self::$store->getUserByUsername('fmulder');
        $login_time = date_create('now');
        $result = self::$authenticator->login('fmulder', 'wrong_pw');
        $user_after = self::$store->getUserByUsername('fmulder');

        $this->assertEquals(
            $user_before->ongoing_pw_fail_count + 1,
            $user_after->ongoing_pw_fail_count
        );
        $this->assertTrue($user_after->last_pw_fail_time >= $login_time);
    }

    public function testUserLocksAfterTooManyBadPasswords()
    {
        $this->triggerLockForUser('fmulder');
        $user = self::$store->getUserByUsername('fmulder');
        $this->assertEquals(true, $user->is_locked);
        $this->assertEquals(
            self::$authenticator->config->login_fail_threshold_count + 1,
            $user->ongoing_pw_fail_count
        );
    }

    public function triggerLockForUser($username)
    {
        $user = self::$store->getUserByUsername($username);
        $times_to_fail = (
            self::$authenticator->config->login_fail_threshold_count
            + 1
            - $user->ongoing_pw_fail_count
        );
        for ($i = 1; $i <= $times_to_fail; $i++) {
            $result = self::$authenticator->login(
                $username,
                'the_wrong_password'
            );
        }
    }

    public function testLoginFailsWhenUserLocked()
    {
        $this->triggerLockForUser('fmulder');
        $result = self::$authenticator->login('fmulder', 'scully');
        $this->assertEquals(true, $result->has_failed);
        $this->assertEquals(
            'This account is locked. Please wait a while then try again ' .
            'or contact your system administrator.',
            $result->message
        );
    }

    public function testLockResetsAfterSetTime()
    {
        $this->triggerLockForUser('fmulder');
        $minutes = self::$authenticator->config->login_fail_lock_minutes;
        $lock_duration = new DateInterval("PT{$minutes}M");
        $fail_time = date_sub(date_create('now'), $lock_duration);
        $user = self::$store->getUserByUsername('fmulder');
        $user->last_pw_fail_time = $fail_time;
        self::$store->updateUser($user);
        $result = self::$authenticator->login('fmulder', 'scully');
        $user_after = self::$store->getUserByUsername('fmulder');
        $this->assertEquals(false, $user_after->is_locked);
    }

    public function testLockDisabledWhenThresholdZero()
    {
        $config = new Config();
        $config->login_fail_threshold_count = 0;
        $this->assertLockDisabledWith($config);
    }

    public function assertLockDisabledWith($config)
    {
        self::$store->updateConfig($config);
        $authenticator = new Authenticator(self::$store);
        $times_to_fail = 50;
        for ($i = 1; $i <= $times_to_fail; $i++) {
            $result = $authenticator->login('fmulder', 'wrong_password');
        }
        $user = self::$store->getUserByUsername('fmulder');
        $this->assertFalse($user->is_locked);
        $this->assertEquals(
            $result->message,
            Authenticator::BAD_PASSWORD_MSG
        );
    }

    public function testLockDisabledWhenLockMinutesZero()
    {
        $config = new Config();
        $config->login_fail_lock_minutes = 0;
        $this->assertLockDisabledWith($config);
    }

    public function testFailsWhenNeedsPasswordChange()
    {
        $result = self::$authenticator->login('dscully', 'mulder');
        $this->assertPasswordExpiredResult($result);
    }

    private function assertPasswordExpiredResult($result)
    {
        $this->assertEquals(true, $result->has_failed);
        $this->assertEquals(
            'The password for this account expired. Please provide a new ' .
            'one using the new password option on the login screen.',
            $result->message
        );
    }

    public function testLoginWithNewPasswordReturnsExpected()
    {
        $result = $this->loginWithNewPassword('fmulder', 'scully');
        $this->assertEquals(false, $result->has_failed);
    }

    private function loginWithNewPassword(
        $username,
        $password,
        $new_password = self::GOOD_NEW_PASSWORD
    ) {
        return self::$authenticator->login(
            $username,
            $password,
            $new_password
        );
    }

    public function testLoginWithNewPasswordChangesIt()
    {
        $this->loginWithNewPassword('fmulder', 'scully');
        $user = self::$store->getUserByUsername('fmulder');
        $this->assertTrue(
            password_verify(self::GOOD_NEW_PASSWORD, $user->pw_hash)
        );
    }

    public function testLoginWithNewPasswordAddsItToHistory()
    {
        $this->loginWithNewPassword('fmulder', 'scully');
        $history = self::$store->getPasswordHistory(101);
        $this->assertTrue(
            password_verify(self::GOOD_NEW_PASSWORD, $history[0]['pw_hash'])
        );
        $this->assertEquals(
            date('Y-m-d'),
            date_format($history[0]['dob'], 'Y-m-d')
        );
    }

    public function testLoginWithNewPasswordClearsNeedsPasswordFlag()
    {
        $this->loginWithNewPassword('dscully', 'mulder');
        $user = self::$store->getUserByUsername('dscully');
        $this->assertEquals(false, $user->needs_pw_change);
    }

    public function testLoginWithNewPasswordSetsLastUpdateTime()
    {
        $this->loginWithNewPassword('dscully', 'mulder');
        $user = self::$store->getUserByUsername('dscully');
        $this->assertEquals(
            date('Y-m-d'),
            date_format($user->last_pw_update_time, 'Y-m-d')
        );
    }

    public function testLoginWithNewTempPasswordDoesIt()
    {
        self::$authenticator->resetPassword(
            'fmulder',
            self::GOOD_NEW_PASSWORD
        );
        $result = self::$authenticator->login(
            'fmulder',
            self::GOOD_NEW_PASSWORD
        );
        $this->assertPasswordExpiredResult($result);
    }

    public function testLoginWithNewPasswordChangesFAPasswordToo()
    {
        $this->loginWithNewPassword('dscully', 'mulder');
        $user = self::$store->getUserByUsername('dscully');
        $this->assertEquals(
            md5(self::GOOD_NEW_PASSWORD),
            $user->fa_pw_hash
        );
    }

    public function testLoginWithNewPasswordFailsWhenTooWeak()
    {
        $weak_new_password = 'password';
        $result = self::$authenticator->login(
            'fmulder',
            'scully',
            $weak_new_password
        );
        $this->assertNewPasswordTooWeakResult($result);
    }

    public function assertNewPasswordTooWeakResult($result)
    {
        $this->assertEquals(true, $result->has_failed);
        $this->assertStringStartsWith(
            'New password is too weak.',
            $result->message
        );
    }

    public function testLoginWithNewPasswordFailureReturnsWithHints()
    {
        $weak_new_password = 'password';
        $result = self::$authenticator->login(
            'fmulder',
            'scully',
            $weak_new_password
        );
        $zxcvbn = new ZxcvbnWrapper();
        $password_hints = $zxcvbn->getPasswordHints('fmulder', 'password');
        $this->assertEquals(
            'New password is too weak. ' . $password_hints,
            $result->message
        );
    }

    public function testLoginWithUnmigratedUserGetsPasswordExpiredFailure()
    {
        $result = self::$authenticator->login('skinner', 'smoking');
        $this->assertPasswordExpiredResult($result);
    }

    public function testLoginWithUnmigratedAndBadPassGetsBadPassFailure()
    {
        $result = self::$authenticator->login('skinner', 'wrong!');
        $this->assertBadPasswordResult($result);
    }

    public function testLoginWithUnmigratedUserWithWeakNewPassword()
    {
        $weak_new_password = 'password';
        $result = self::$authenticator->login(
            'skinner',
            'smoking',
            $weak_new_password
        );
        $this->assertNewPasswordTooWeakResult($result);
    }

    public function testGoodLoginWithUnmigratedUserReturnsSuccessful()
    {
        $result = $this->performGoodUnmigratedUserLogin();
        $this->assertEquals(false, $result->has_failed);
    }

    private function performGoodUnmigratedUserLogin()
    {
        return self::$authenticator->login(
            'skinner',
            'smoking',
            self::GOOD_NEW_PASSWORD
        );
    }

    public function testLoginWithUnmigratedUserMigratesHim()
    {
        $result = $this->performGoodUnmigratedUserLogin();
        $user = self::$store->getUserByUsername('skinner');
        $this->assertEquals(
            md5(self::GOOD_NEW_PASSWORD),
            $user->fa_pw_hash
        );
        $this->assertTrue(
            password_verify(self::GOOD_NEW_PASSWORD, $user->pw_hash)
        );
        $this->assertEquals(false, $user->needs_pw_change);
        $this->assertEquals(false, $user->is_locked);
        $this->assertEquals(0, $user->ongoing_pw_fail_count);
        $this->assertEquals(null, $user->last_pw_fail_time);
        $this->assertEquals(
            date('Y-m-d'),
            date_format($user->last_pw_update_time, 'Y-m-d')
        );
    }

    public function testLoginWithUnmigratedUserAddsNewPasswordToHistory()
    {
        $this->performGoodUnmigratedUserLogin();
        $history = self::$store->getPasswordHistory(103);
        $this->assertTrue(
            password_verify(self::GOOD_NEW_PASSWORD, $history[0]['pw_hash'])
        );
        $this->assertEquals(
            date('Y-m-d'),
            date_format($history[0]['dob'], 'Y-m-d')
        );
    }

    public function testLoginWithUnmigratedUserWithTempPasswordDoesIt()
    {
        $is_temporary = true;
        self::$authenticator->resetPassword(
            'skinner',
            self::GOOD_NEW_PASSWORD
        );
        $result = self::$authenticator->login(
            'skinner',
            self::GOOD_NEW_PASSWORD
        );
        $this->assertPasswordExpiredResult($result);
    }

    public function testLoginWithOldPasswordForcesUpdate()
    {
        $this->makeUsersPasswordTooOld('fmulder');
        $result = self::$authenticator->login('fmulder', 'scully');
        $this->assertPasswordExpiredResult($result);
    }

    public function makeUsersPasswordTooOld($username)
    {
        $user = self::$store->getUserByUsername($username);
        $user->last_pw_update_time = $this->getDateForPasswordTooOld();
        self::$store->updateUser($user);
    }

    public function getDateForPasswordTooOld()
    {
        $days_old = Config::DEFAULT_MAXIMUM_PASSWORD_AGE_DAYS + 1;
        $today = new DateTime();
        return $today->sub(new DateInterval('P' . $days_old . 'D'));
    }

    public function testLoginWithNewPasswordFailsWhenMatchesExisting()
    {
        $result = $this->loginWithNewPassword(
            'fmulder',
            'scully',
            'scully'
        );
        $this->assertNeedNewPasswordResult($result);
    }

    public function assertNeedNewPasswordResult($result)
    {
        $this->assertEquals(true, $result->has_failed);
        $this->assertEquals(
            'This password matches one of your recently used ones. ' .
            'Please create a new password.',
            $result->message
        );
    }

    public function testOnlyMostRecentPasswordsCheckedInHistory()
    {
        $passwords_to_add = Config::DEFAULT_PASSWORD_HISTORY_COUNT + 1;
        $last_password = 'smoking';
        for ($i = 1; $i <= $passwords_to_add; $i++) {
            $new_password = self::GOOD_NEW_PASSWORD . "_$i";
            $this->loginWithNewPassword(
                'skinner',
                $last_password,
                $new_password
            );
            $last_password = $new_password;
        }
        $result = $this->loginWithNewPassword(
            'skinner',
            $last_password,
            self::GOOD_NEW_PASSWORD . "_1"
        );
        $this->assertEquals(false, $result->has_failed);
    }

    public function testChangePasswordUnexpectedExceptionResult()
    {
        $method_name = 'changePassword';
        $this->assertHandlesUnexpectedException($method_name);
    }

    public function testChangePasswordChangesIt()
    {
        $new_password = self::GOOD_NEW_PASSWORD;
        self::$authenticator->changePassword('fmulder', $new_password);
        $user = self::$store->getUserByUsername('fmulder');
        $this->assertPasswordChanged($user, $new_password);
    }

    public function assertPasswordChanged($user, $new_password)
    {
        $this->assertTrue(
            password_verify($new_password, $user->pw_hash)
        );
    }

    public function testResetPasswordUnexpectedExceptionResult()
    {
        $method_name = 'resetPassword';
        $this->assertHandlesUnexpectedException($method_name);
    }

    public function testResetPasswordResetsIt()
    {
        $new_password = self::GOOD_NEW_PASSWORD;
        self::$authenticator->resetPassword('fmulder', $new_password);
        $user = self::$store->getUserByUsername('fmulder');
        $this->assertPasswordChanged($user, $new_password);
        $this->assertTrue($user->needs_pw_change);
    }
}
