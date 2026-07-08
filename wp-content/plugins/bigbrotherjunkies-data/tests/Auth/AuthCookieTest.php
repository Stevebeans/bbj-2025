<?php
namespace BigBrotherJunkies\Data\Tests\Auth;

use BigBrotherJunkies\Data\Auth\AuthCookie;
use Brain\Monkey;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

final class AuthCookieTest extends TestCase {
    private const SECRET = 'test-secret-key-for-authcookie';

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        if (!defined('JWT_AUTH_SECRET_KEY')) {
            define('JWT_AUTH_SECRET_KEY', self::SECRET);
        }
        unset($_COOKIE[AuthCookie::TOKEN_COOKIE]);
    }

    protected function tearDown(): void {
        unset($_COOKIE[AuthCookie::TOKEN_COOKIE]);
        AuthCookie::$headerSender = null;
        Monkey\tearDown();
        parent::tearDown();
    }

    private function makeToken(int $userId, int $expOffset = 3600): string {
        return JWT::encode([
            'iss' => 'https://wp.bigbrotherjunkies.com',
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + $expOffset,
            'data' => ['user' => ['id' => $userId]],
        ], self::SECRET, 'HS256');
    }

    // --- buildHeaders ---

    public function test_remembered_anchor_has_max_age_httponly_secure_and_no_domain(): void {
        [$anchor, $hint] = AuthCookie::buildHeaders('JWT123', true, 'wp.bigbrotherjunkies.com');
        $this->assertSame(
            'Set-Cookie: bbj_token_s=JWT123; Path=/; Max-Age=2592000; SameSite=Lax; HttpOnly; Secure',
            $anchor
        );
        $this->assertStringNotContainsString('Domain', $anchor); // host-only by design (staging/prod isolation)
        $this->assertSame(
            'Set-Cookie: bbj_session_hint=1; Path=/; Max-Age=2592000; SameSite=Lax; Secure; Domain=.bigbrotherjunkies.com',
            $hint
        );
    }

    public function test_session_login_omits_max_age_on_both_cookies(): void {
        [$anchor, $hint] = AuthCookie::buildHeaders('JWT123', false, 'wp.bigbrotherjunkies.com');
        $this->assertStringNotContainsString('Max-Age', $anchor);
        $this->assertStringNotContainsString('Max-Age', $hint);
        $this->assertStringContainsString('HttpOnly', $anchor);
    }

    public function test_localhost_host_omits_secure_and_hint_domain(): void {
        [$anchor, $hint] = AuthCookie::buildHeaders('JWT123', true, 'bbj.localhost');
        $this->assertStringNotContainsString('Secure', $anchor);
        $this->assertStringNotContainsString('Domain', $hint);
    }

    public function test_clear_headers_expire_both_cookies(): void {
        [$anchor, $hint] = AuthCookie::buildClearHeaders('wp.bigbrotherjunkies.com');
        $this->assertSame('Set-Cookie: bbj_token_s=; Path=/; Max-Age=0; SameSite=Lax; HttpOnly; Secure', $anchor);
        $this->assertSame('Set-Cookie: bbj_session_hint=; Path=/; Max-Age=0; SameSite=Lax; Secure; Domain=.bigbrotherjunkies.com', $hint);
    }

    // --- resolveUserIdFromToken ---

    public function test_valid_token_resolves_user_id(): void {
        $this->assertSame(42, AuthCookie::resolveUserIdFromToken($this->makeToken(42)));
    }

    public function test_expired_token_resolves_null(): void {
        $this->assertNull(AuthCookie::resolveUserIdFromToken($this->makeToken(42, -3600)));
    }

    public function test_garbage_and_missing_tokens_resolve_null(): void {
        $this->assertNull(AuthCookie::resolveUserIdFromToken('not.a.jwt'));
        $this->assertNull(AuthCookie::resolveUserIdFromToken(''));
        $this->assertNull(AuthCookie::resolveUserIdFromToken(null));
    }

    public function test_token_signed_with_wrong_secret_resolves_null(): void {
        $forged = JWT::encode(
            ['exp' => time() + 3600, 'data' => ['user' => ['id' => 42]]],
            'attacker-secret', 'HS256'
        );
        $this->assertNull(AuthCookie::resolveUserIdFromToken($forged));
    }

    // --- resolveUserId (reads $_COOKIE) ---

    public function test_resolve_user_id_reads_anchor_cookie(): void {
        $_COOKIE[AuthCookie::TOKEN_COOKIE] = $this->makeToken(7);
        $this->assertSame(7, AuthCookie::resolveUserId());
    }

    public function test_resolve_user_id_null_when_cookie_absent(): void {
        $this->assertNull(AuthCookie::resolveUserId());
    }

    // --- emit / clear via header seam ---

    public function test_emit_sends_two_headers_via_seam(): void {
        Monkey\Functions\when('home_url')->justReturn('https://wp.bigbrotherjunkies.com');
        $sent = [];
        AuthCookie::$headerSender = function (string $h) use (&$sent) { $sent[] = $h; };
        AuthCookie::emit('JWT123', true);
        $this->assertCount(2, $sent);
        $this->assertStringContainsString('bbj_token_s=JWT123', $sent[0]);
        $this->assertStringContainsString('bbj_session_hint=1', $sent[1]);
    }

    public function test_clear_sends_two_expiring_headers_via_seam(): void {
        Monkey\Functions\when('home_url')->justReturn('https://wp.bigbrotherjunkies.com');
        $sent = [];
        AuthCookie::$headerSender = function (string $h) use (&$sent) { $sent[] = $h; };
        AuthCookie::clear();
        $this->assertCount(2, $sent);
        $this->assertStringContainsString('Max-Age=0', $sent[0]);
        $this->assertStringContainsString('Max-Age=0', $sent[1]);
    }
}
