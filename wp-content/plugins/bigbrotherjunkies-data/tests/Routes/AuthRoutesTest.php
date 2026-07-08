<?php
namespace BigBrotherJunkies\Data\Tests\Routes;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

final class AuthRoutesTest extends TestCase {
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_refresh_nonce_returns_fresh_nonce_for_logged_in_user(): void {
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('wp_create_nonce')->alias(fn($a) => "nonce-for-{$a}");
        $route = new \BigBrotherJunkies\Data\Api\AuthRoutes();
        $response = $route->refreshNonce();
        $this->assertSame(['nonce' => 'nonce-for-bbj_comments'], $response->get_data());
        $this->assertSame(200, $response->get_status());
    }

    public function test_refresh_nonce_rejects_anonymous(): void {
        Functions\when('is_user_logged_in')->justReturn(false);
        $route = new \BigBrotherJunkies\Data\Api\AuthRoutes();
        $this->assertFalse($route->checkUserLoggedIn());
    }

    public function test_refresh_emits_anchor_cookie_alongside_token(): void {
        if (!defined('JWT_AUTH_SECRET_KEY')) {
            define('JWT_AUTH_SECRET_KEY', 'test-secret-key-for-authcookie');
        }
        if (!defined('DAY_IN_SECONDS')) {
            define('DAY_IN_SECONDS', 86400);
        }
        Functions\when('home_url')->justReturn('https://wp.bigbrotherjunkies.com');
        Functions\when('get_bloginfo')->justReturn('https://wp.bigbrotherjunkies.com');
        Functions\when('__')->returnArg();

        $user = new \WP_User();
        $user->ID = 42;
        $user->display_name = 'Tester';
        $user->roles = ['subscriber'];
        Functions\when('wp_get_current_user')->justReturn($user);

        $sent = [];
        \BigBrotherJunkies\Data\Auth\AuthCookie::$headerSender = function (string $h) use (&$sent) { $sent[] = $h; };

        $request = new \WP_REST_Request(['remember_me' => true]);

        try {
            $route = new \BigBrotherJunkies\Data\Api\AuthRoutes();
            $response = $route->handleRefreshToken($request);
            $this->assertSame(200, $response->get_status());
            $this->assertArrayHasKey('token', $response->get_data());
            $this->assertCount(2, $sent);
            $this->assertStringContainsString('bbj_token_s=', $sent[0]);
            $this->assertStringContainsString('HttpOnly', $sent[0]);
            $this->assertStringContainsString('bbj_session_hint=1', $sent[1]);
        } finally {
            \BigBrotherJunkies\Data\Auth\AuthCookie::$headerSender = null;
        }
    }
}
