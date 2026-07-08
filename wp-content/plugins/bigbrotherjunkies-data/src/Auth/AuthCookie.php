<?php
namespace BigBrotherJunkies\Data\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Server-set HttpOnly "anchor" cookie (Auth Phase 2).
 *
 * The JS bbj_token cookie remains the working credential; this anchor exists so
 * the client can silently re-mint it after security suites (ESET) or Safari ITP
 * wipe script-written cookies. Host-only on purpose: credentialed fetches key
 * cookies to the WP API host, staging and prod share the JWT secret (a
 * Domain-wide staging anchor would ride along on prod requests), and host-only
 * keeps the anchor off Vercel requests entirely.
 *
 * bbj_session_hint is the JS-readable companion (Domain-wide) that tells the
 * frontend "an anchor may exist" — without it, every anonymous pageview would
 * fire a speculative /auth/refresh at WP.
 */
class AuthCookie
{
    public const TOKEN_COOKIE = 'bbj_token_s';
    public const HINT_COOKIE  = 'bbj_session_hint';

    private const THIRTY_DAYS = 2592000;

    /** @var callable|null Test seam; null sends via header($h, false). */
    public static $headerSender = null;

    public static function emit(string $jwt, bool $remember): void
    {
        foreach (self::buildHeaders($jwt, $remember, self::host()) as $header) {
            self::send($header);
        }
    }

    public static function clear(): void
    {
        foreach (self::buildClearHeaders(self::host()) as $header) {
            self::send($header);
        }
    }

    /** Validate the anchor cookie's JWT; null unless it yields a user id. */
    public static function resolveUserId(): ?int
    {
        return self::resolveUserIdFromToken($_COOKIE[self::TOKEN_COOKIE] ?? null);
    }

    /**
     * @return string[] [anchorHeader, hintHeader]
     */
    public static function buildHeaders(string $jwt, bool $remember, string $host): array
    {
        $maxAge = $remember ? '; Max-Age=' . self::THIRTY_DAYS : '';
        $secure = self::isLocalHost($host) ? '' : '; Secure';
        $domain = self::hintDomain($host);

        return [
            "Set-Cookie: " . self::TOKEN_COOKIE . "={$jwt}; Path=/{$maxAge}; SameSite=Lax; HttpOnly{$secure}",
            "Set-Cookie: " . self::HINT_COOKIE . "=1; Path=/{$maxAge}; SameSite=Lax{$secure}{$domain}",
        ];
    }

    /**
     * @return string[] [anchorHeader, hintHeader] with Max-Age=0
     */
    public static function buildClearHeaders(string $host): array
    {
        $secure = self::isLocalHost($host) ? '' : '; Secure';
        $domain = self::hintDomain($host);

        return [
            "Set-Cookie: " . self::TOKEN_COOKIE . "=; Path=/; Max-Age=0; SameSite=Lax; HttpOnly{$secure}",
            "Set-Cookie: " . self::HINT_COOKIE . "=; Path=/; Max-Age=0; SameSite=Lax{$secure}{$domain}",
        ];
    }

    public static function resolveUserIdFromToken(?string $token): ?int
    {
        if (!$token) {
            return null;
        }
        $secret = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : false;
        if (!$secret) {
            return null;
        }
        try {
            $algorithm = function_exists('apply_filters')
                ? apply_filters('jwt_auth_algorithm', 'HS256')
                : 'HS256';
            $decoded = JWT::decode($token, new Key($secret, $algorithm));
            $id = $decoded->data->user->id ?? null;
            return $id ? (int) $id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function send(string $header): void
    {
        $sender = self::$headerSender;
        if ($sender !== null) {
            $sender($header);
            return;
        }
        // replace=false — never clobber WpSessionBridge's WP-native auth
        // cookies on wp_session=1 logins (multiple Set-Cookie headers stack).
        header($header, false);
    }

    private static function host(): string
    {
        $host = parse_url(home_url(), PHP_URL_HOST);
        return is_string($host) ? $host : '';
    }

    private static function isLocalHost(string $host): bool
    {
        return $host === 'localhost' || str_ends_with($host, '.localhost');
    }

    /** Hint must be readable by apex-page JS → Domain-wide on real hosts only. */
    private static function hintDomain(string $host): string
    {
        return str_ends_with($host, 'bigbrotherjunkies.com')
            ? '; Domain=.bigbrotherjunkies.com'
            : '';
    }
}
