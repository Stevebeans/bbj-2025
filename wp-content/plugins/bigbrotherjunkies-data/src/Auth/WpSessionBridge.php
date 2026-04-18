<?php

namespace BigBrotherJunkies\Data\Auth;

/**
 * Bridges the plugin's JWT-based auth endpoints with WordPress-native
 * session cookies. When a request carries wp_session=1, we call
 * wp_set_auth_cookie() so is_user_logged_in() works server-side for
 * the PHP theme. The Next.js app doesn't send the flag, so its flow
 * is unchanged.
 */
class WpSessionBridge
{
    /**
     * If the request opts in, set the WordPress auth cookie for $userId.
     *
     * @param int              $userId    User ID to log in.
     * @param bool             $remember  14-day cookie vs session cookie.
     * @param \WP_REST_Request $request   Incoming REST request.
     * @return bool  true if the cookie was set, false otherwise.
     */
    public static function maybeSetAuthCookie(int $userId, bool $remember, \WP_REST_Request $request): bool
    {
        if ((int) $request->get_param('wp_session') !== 1) {
            return false;
        }
        if ($userId <= 0 || !get_userdata($userId)) {
            return false;
        }
        wp_set_current_user($userId);
        wp_set_auth_cookie($userId, $remember, is_ssl());
        return true;
    }

    /**
     * Verify the theme-scoped auth nonce when wp_session=1 is requested.
     * Returns null on success, WP_Error on failure. Endpoint callers should
     * call this first and return the error if non-null.
     */
    public static function verifyNonce(\WP_REST_Request $request): ?\WP_Error
    {
        if ((int) $request->get_param('wp_session') !== 1) {
            return null; // Only enforce for theme consumers opting into WP session.
        }
        // Read from X-BBJ-Nonce (not X-WP-Nonce) because WP core's
        // rest_cookie_check_errors intercepts X-WP-Nonce and validates against
        // the 'wp_rest' action, which would reject our 'bbj_auth' nonce
        // whenever the browser carries any WP cookie.
        $nonce = $request->get_header('X-BBJ-Nonce') ?: (string) $request->get_param('_wpnonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'bbj_auth')) {
            return new \WP_Error(
                'invalid_nonce',
                __('Session expired. Please refresh the page.', 'bigbrotherjunkies-data'),
                ['status' => 403]
            );
        }
        return null;
    }

    /**
     * Register the SameSite=Lax enforcement filter once.
     * Call this from Plugin::initAuth() during bootstrap.
     */
    public static function init(): void
    {
        add_filter('set_auth_cookie', [self::class, 'addSameSite'], 10, 6);
        add_filter('set_logged_in_cookie', [self::class, 'addSameSite'], 10, 6);
    }

    /**
     * Append SameSite=Lax to auth cookie headers. WordPress core does not
     * set SameSite by default, and some WebViews require Lax for survival.
     *
     * @param string $cookie  Serialized cookie string.
     */
    public static function addSameSite($cookie): string
    {
        if (stripos((string) $cookie, 'samesite=') !== false) {
            return (string) $cookie;
        }
        return rtrim((string) $cookie, ';') . '; SameSite=Lax';
    }
}
