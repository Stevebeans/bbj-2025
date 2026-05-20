<?php
namespace BigBrotherJunkies\Data\Tests\Auth;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real bbjd_jwt_present_and_valid(). Runs in a separate process so
 * defining the real function does not collide with CookieOrJwtAuthTest, which
 * mocks the same name via Brain Monkey (and therefore needs it undefined).
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class JwtPresenceTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        require_once __DIR__ . '/../../src/Auth/JwtPresence.php';
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        parent::tearDown();
    }

    public function test_true_when_bearer_present_and_user_logged_in(): void {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc.def.ghi';
        Functions\when('is_user_logged_in')->justReturn(true);
        $this->assertTrue(\bbjd_jwt_present_and_valid());
    }

    public function test_false_when_bearer_present_but_not_logged_in(): void {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc.def.ghi';
        Functions\when('is_user_logged_in')->justReturn(false);
        $this->assertFalse(\bbjd_jwt_present_and_valid());
    }

    public function test_false_when_no_authorization_header(): void {
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        // Logged in via cookie, but no Bearer: must fall through to the nonce path.
        Functions\when('is_user_logged_in')->justReturn(true);
        $this->assertFalse(\bbjd_jwt_present_and_valid());
    }

    public function test_false_when_non_bearer_authorization(): void {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic dXNlcjpwYXNz';
        Functions\when('is_user_logged_in')->justReturn(true);
        $this->assertFalse(\bbjd_jwt_present_and_valid());
    }

    public function test_reads_redirect_authorization_fallback(): void {
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer abc.def.ghi';
        Functions\when('is_user_logged_in')->justReturn(true);
        $this->assertTrue(\bbjd_jwt_present_and_valid());
    }
}
