<?php
/**
 * Cookie+JWT permission helper for bbjd/v1 REST endpoints.
 *
 * The class lives in BigBrotherJunkies\Data\Auth (PSR-4 autoloaded).
 * Two procedural wrappers are declared in the global namespace so they
 * can be used directly as REST permission_callback values.
 */

namespace BigBrotherJunkies\Data\Auth {

    if (!defined('ABSPATH')) {
        exit;
    }

    class CookieOrJwtAuth {
        private static string $lastPath = 'none';

        public static function check(): bool {
            $nonce = $_SERVER['HTTP_X_BBJ_NONCE'] ?? '';
            if (is_user_logged_in() && $nonce !== '' && wp_verify_nonce($nonce, 'bbj_comments')) {
                self::$lastPath = 'cookie';
                return true;
            }
            if (function_exists('bbjd_jwt_present_and_valid') && bbjd_jwt_present_and_valid()) {
                self::$lastPath = 'jwt';
                return true;
            }
            self::$lastPath = 'none';
            return false;
        }

        public static function lastPath(): string { return self::$lastPath; }
    }
}

namespace {
    // Procedural wrappers — global namespace so REST permission_callback can
    // reference them without a namespace prefix.
    if (!function_exists('bbjd_cookie_or_jwt_permission')) {
        function bbjd_cookie_or_jwt_permission(): bool {
            return \BigBrotherJunkies\Data\Auth\CookieOrJwtAuth::check();
        }
    }
    if (!function_exists('bbjd_last_auth_path')) {
        function bbjd_last_auth_path(): string {
            return \BigBrotherJunkies\Data\Auth\CookieOrJwtAuth::lastPath();
        }
    }
}
