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
        $this->assertFalse($route->checkLoggedIn());
    }
}
