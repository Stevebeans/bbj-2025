<?php
namespace BigBrotherJunkies\Data\Tests\Auth;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class CookieOrJwtAuthTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_returns_true_when_logged_in_with_valid_nonce(): void {
        $_SERVER['HTTP_X_BBJ_NONCE'] = 'valid-nonce';
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('wp_verify_nonce')->alias(
            fn($n, $action) => $n === 'valid-nonce' && $action === 'bbj_comments' ? 1 : false
        );
        $this->assertTrue(\bbjd_cookie_or_jwt_permission());
    }

    public function test_returns_false_when_logged_in_without_nonce(): void {
        unset($_SERVER['HTTP_X_BBJ_NONCE']);
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('bbjd_jwt_present_and_valid')->justReturn(false);
        $this->assertFalse(\bbjd_cookie_or_jwt_permission());
    }

    public function test_returns_false_when_logged_in_with_bad_nonce(): void {
        $_SERVER['HTTP_X_BBJ_NONCE'] = 'wrong';
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('wp_verify_nonce')->justReturn(false);
        Functions\when('bbjd_jwt_present_and_valid')->justReturn(false);
        $this->assertFalse(\bbjd_cookie_or_jwt_permission());
    }

    public function test_returns_true_with_valid_jwt_only(): void {
        unset($_SERVER['HTTP_X_BBJ_NONCE']);
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('bbjd_jwt_present_and_valid')->justReturn(true);
        $this->assertTrue(\bbjd_cookie_or_jwt_permission());
    }

    public function test_returns_false_with_invalid_jwt_no_cookie(): void {
        unset($_SERVER['HTTP_X_BBJ_NONCE']);
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('bbjd_jwt_present_and_valid')->justReturn(false);
        $this->assertFalse(\bbjd_cookie_or_jwt_permission());
    }

    public function test_prefers_cookie_when_both_present(): void {
        $_SERVER['HTTP_X_BBJ_NONCE'] = 'valid-nonce';
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('bbjd_jwt_present_and_valid')->justReturn(true);
        $this->assertTrue(\bbjd_cookie_or_jwt_permission());
        $this->assertSame('cookie', \bbjd_last_auth_path());
    }
}
