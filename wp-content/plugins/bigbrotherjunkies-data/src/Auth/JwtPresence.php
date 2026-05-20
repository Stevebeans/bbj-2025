<?php
/**
 * JWT presence check for bbjd/v1 REST permission callbacks.
 *
 * Defined in its own file (loaded in production by the main plugin) rather than
 * in CookieOrJwtAuth.php so the CookieOrJwtAuth unit tests can keep mocking
 * bbjd_jwt_present_and_valid() via Brain Monkey, which requires the function to
 * be undefined at test time.
 */

namespace {
    if (!defined('ABSPATH')) {
        exit;
    }

    if (!function_exists('bbjd_jwt_present_and_valid')) {
        /**
         * True when the request carries a Bearer token AND that token authenticated
         * a user. The jwt-auth plugin validates the token on determine_current_user,
         * so a valid Bearer leaves is_user_logged_in() === true by the time a REST
         * permission_callback runs.
         *
         * Requiring the Bearer header is what keeps the cookie+nonce CSRF design in
         * CookieOrJwtAuth intact: a cookie-only request (no Bearer) must pass through
         * the nonce path, never this one.
         */
        function bbjd_jwt_present_and_valid(): bool {
            $auth = '';
            if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $auth = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $auth = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (function_exists('getallheaders')) {
                foreach (getallheaders() as $key => $value) {
                    if (strtolower($key) === 'authorization') {
                        $auth = $value;
                        break;
                    }
                }
            }

            if (stripos($auth, 'Bearer ') !== 0) {
                return false;
            }

            return is_user_logged_in();
        }
    }
}
