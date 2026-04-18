<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Comments\RankCalculator;
use BigBrotherJunkies\Data\Comments\AvatarUploader;

/**
 * User Settings API Routes
 *
 * Provides endpoints for user settings management:
 * - Profile data (display name, bio, favorite player)
 * - Notification preferences
 * - Email change with verification
 * - Player search for favorite player dropdown
 * - Help/FAQ data with rank definitions
 */
class SettingsRoutes
{
    /**
     * Notification preference meta keys
     */
    private const NOTIFICATION_KEYS = [
        'new_reply' => 'bbj_notify_replies',
        'new_mention' => 'bbj_notify_mentions',
        'new_message' => 'bbj_notify_messages',
        'feed_updates' => 'bbj_notify_feed_updates',
        'newsletter' => 'bbj_notify_newsletter',
    ];

    /**
     * Email verification transient prefix
     */
    private const EMAIL_VERIFY_PREFIX = 'bbj_email_verify_';

    /**
     * Email verification expiry in seconds (1 hour)
     */
    private const EMAIL_VERIFY_EXPIRY = 3600;

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $namespace = 'bbjd/v1';

        // Get all settings for current user
        register_rest_route($namespace, '/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'getSettings'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
        ]);

        // Update profile settings
        register_rest_route($namespace, '/settings', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateSettings'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
        ]);

        // Update notification preferences
        register_rest_route($namespace, '/settings/notifications', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateNotifications'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
        ]);

        // Request email change (sends verification)
        register_rest_route($namespace, '/settings/email', [
            'methods' => 'POST',
            'callback' => [$this, 'requestEmailChange'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
        ]);

        // Verify email change
        register_rest_route($namespace, '/settings/email/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'verifyEmailChange'],
            'permission_callback' => '__return_true', // Token-based auth
        ]);

        // Search players for favorite player dropdown
        register_rest_route($namespace, '/settings/players/search', [
            'methods' => 'GET',
            'callback' => [$this, 'searchPlayers'],
            'permission_callback' => [$this, 'checkUserLoggedIn'],
            'args' => [
                'q' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Get help/FAQ data including rank definitions
        register_rest_route($namespace, '/settings/help', [
            'methods' => 'GET',
            'callback' => [$this, 'getHelpData'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get all settings for current user
     */
    public function getSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $user = get_user_by('ID', $userId);

        if (!$user) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Get user meta
        $firstName = get_user_meta($userId, 'first_name', true);
        $lastName = get_user_meta($userId, 'last_name', true);
        $bio = get_user_meta($userId, 'description', true);
        $favoritePlayerId = get_user_meta($userId, 'bbj_favorite_player_id', true);

        // Get favorite player details if set
        $favoritePlayer = null;
        if ($favoritePlayerId) {
            $favoritePlayer = $this->getPlayerById((int) $favoritePlayerId);
        }

        // Get notification preferences
        $notifications = [];
        foreach (self::NOTIFICATION_KEYS as $key => $metaKey) {
            $value = get_user_meta($userId, $metaKey, true);
            // Default to true for replies/mentions/messages, false for others
            if ($value === '') {
                $notifications[$key] = in_array($key, ['new_reply', 'new_mention', 'new_message']);
            } else {
                $notifications[$key] = (bool) $value;
            }
        }

        // Get rank info
        $rank = RankCalculator::calculateRank($userId);

        // Check if user is supporter based on supporter roles setting
        $adSettings = get_option('bbjd_ad_settings', []);
        $supporterRoles = $adSettings['global_hidden_roles'] ?? [];
        $isSupporter = !empty(array_intersect($user->roles, $supporterRoles));

        // Get avatar URL (uses our new system with fallback)
        $avatarUrl = AvatarUploader::getAvatarUrl($userId, 96);
        $hasCustomAvatar = AvatarUploader::hasCustomAvatar($userId);

        // Check if registered via Google
        $googleId = get_user_meta($userId, 'bbj_google_id', true);
        $registeredVia = $googleId ? 'google' : 'email';

        // Get supporter date if applicable
        $supporterSince = null;
        if ($isSupporter) {
            $supporterSince = get_user_meta($userId, 'bbj_supporter_since', true);
        }

        // Get gifts given (placeholder for now)
        $giftsGiven = (int) get_user_meta($userId, 'bbj_gifts_given', true);

        return new \WP_REST_Response([
            'success' => true,
            'settings' => [
                'profile' => [
                    'id' => $userId,
                    'username' => $user->user_login,
                    'email' => $user->user_email,
                    'display_name' => $user->display_name,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'bio' => $bio,
                    'avatar_url' => $avatarUrl,
                    'has_custom_avatar' => $hasCustomAvatar,
                    'favorite_player' => $favoritePlayer,
                    'registered_date' => $user->user_registered,
                    'registered_via' => $registeredVia,
                ],
                'notifications' => $notifications,
                'premium' => [
                    'is_supporter' => $isSupporter,
                    'supporter_since' => $supporterSince,
                    'gifts_given' => $giftsGiven,
                ],
                'rank' => [
                    'key' => $rank['key'],
                    'name' => $rank['name'],
                    'color' => $rank['color'],
                    'bg_color' => $rank['bg_color'],
                    'icon' => $rank['icon'] ?? null,
                    'is_special' => $rank['is_special'],
                    'stats' => $rank['stats'] ?? null,
                    'next_rank' => $rank['next_rank'] ?? null,
                ],
            ],
        ], 200);
    }

    /**
     * Update profile settings
     */
    public function updateSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $params = $request->get_json_params();

        $updated = [];

        // Update display name
        if (isset($params['display_name'])) {
            $displayName = sanitize_text_field($params['display_name']);
            if (strlen($displayName) >= 2 && strlen($displayName) <= 50) {
                wp_update_user([
                    'ID' => $userId,
                    'display_name' => $displayName,
                ]);
                $updated['display_name'] = $displayName;
            }
        }

        // Update first name
        if (isset($params['first_name'])) {
            $firstName = sanitize_text_field($params['first_name']);
            update_user_meta($userId, 'first_name', $firstName);
            $updated['first_name'] = $firstName;
        }

        // Update last name
        if (isset($params['last_name'])) {
            $lastName = sanitize_text_field($params['last_name']);
            update_user_meta($userId, 'last_name', $lastName);
            $updated['last_name'] = $lastName;
        }

        // Update bio
        if (isset($params['bio'])) {
            $bio = sanitize_textarea_field($params['bio']);
            if (strlen($bio) <= 500) {
                update_user_meta($userId, 'description', $bio);
                $updated['bio'] = $bio;
            }
        }

        // Update favorite player
        if (array_key_exists('favorite_player_id', $params)) {
            $playerId = $params['favorite_player_id'];
            if ($playerId === null || $playerId === '') {
                delete_user_meta($userId, 'bbj_favorite_player_id');
                $updated['favorite_player'] = null;
            } else {
                $playerId = (int) $playerId;
                // Verify player exists
                $player = $this->getPlayerById($playerId);
                if ($player) {
                    update_user_meta($userId, 'bbj_favorite_player_id', $playerId);
                    $updated['favorite_player'] = $player;
                } else {
                    return new \WP_REST_Response([
                        'success' => false,
                        'message' => 'Invalid player ID',
                    ], 400);
                }
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Settings updated',
            'updated' => $updated,
        ], 200);
    }

    /**
     * Update notification preferences
     */
    public function updateNotifications(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $user = get_user_by('ID', $userId);
        $params = $request->get_json_params();

        // Check if user is supporter based on supporter roles setting
        $adSettings = get_option('bbjd_ad_settings', []);
        $supporterRoles = $adSettings['global_hidden_roles'] ?? [];
        $isSupporter = !empty(array_intersect($user->roles, $supporterRoles));

        $updated = [];

        foreach (self::NOTIFICATION_KEYS as $key => $metaKey) {
            if (isset($params[$key])) {
                $value = (bool) $params[$key];

                // Check premium restriction for feed_updates
                if ($key === 'feed_updates' && $value && !$isSupporter) {
                    return new \WP_REST_Response([
                        'success' => false,
                        'message' => 'Feed update notifications are a premium feature',
                        'code' => 'premium_required',
                    ], 403);
                }

                update_user_meta($userId, $metaKey, $value ? '1' : '0');
                $updated[$key] = $value;
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Notification preferences updated',
            'updated' => $updated,
        ], 200);
    }

    /**
     * Request email change - sends verification email
     */
    public function requestEmailChange(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = get_current_user_id();
        $params = $request->get_json_params();

        $newEmail = sanitize_email($params['email'] ?? '');

        if (!is_email($newEmail)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid email address',
            ], 400);
        }

        // Check if email already in use
        if (email_exists($newEmail)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'This email address is already in use',
            ], 400);
        }

        // Rate limit: one request per hour
        $lastRequest = get_user_meta($userId, 'bbj_email_change_requested', true);
        if ($lastRequest && (time() - (int) $lastRequest) < 3600) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Please wait before requesting another email change',
            ], 429);
        }

        // Generate verification token
        $token = wp_generate_password(32, false);
        $data = [
            'user_id' => $userId,
            'new_email' => $newEmail,
            'token' => $token,
        ];

        // Store in transient
        set_transient(self::EMAIL_VERIFY_PREFIX . $token, $data, self::EMAIL_VERIFY_EXPIRY);
        update_user_meta($userId, 'bbj_email_change_requested', time());

        // Send verification email
        $verifyUrl = add_query_arg([
            'action' => 'verify_email',
            'token' => $token,
        ], home_url('/settings'));

        $user = get_user_by('ID', $userId);
        $siteName = get_bloginfo('name');

        $subject = "Verify your new email address - {$siteName}";
        $message = "Hi {$user->display_name},\n\n";
        $message .= "You requested to change your email address to {$newEmail}.\n\n";
        $message .= "Click the link below to verify this change:\n";
        $message .= $verifyUrl . "\n\n";
        $message .= "This link expires in 1 hour.\n\n";
        $message .= "If you didn't request this change, you can ignore this email.\n\n";
        $message .= "- {$siteName}";

        wp_mail($newEmail, $subject, $message);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Verification email sent to ' . $newEmail,
        ], 200);
    }

    /**
     * Verify email change with token
     */
    public function verifyEmailChange(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $token = sanitize_text_field($params['token'] ?? '');

        if (empty($token)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid token',
            ], 400);
        }

        // Get verification data
        $data = get_transient(self::EMAIL_VERIFY_PREFIX . $token);

        if (!$data) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Token expired or invalid. Please request a new verification email.',
            ], 400);
        }

        // Double check email not taken (race condition protection)
        if (email_exists($data['new_email'])) {
            delete_transient(self::EMAIL_VERIFY_PREFIX . $token);
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'This email address is now in use by another account',
            ], 400);
        }

        // Update email
        $result = wp_update_user([
            'ID' => $data['user_id'],
            'user_email' => $data['new_email'],
        ]);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to update email: ' . $result->get_error_message(),
            ], 500);
        }

        // Clean up
        delete_transient(self::EMAIL_VERIFY_PREFIX . $token);
        delete_user_meta($data['user_id'], 'bbj_email_change_requested');

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Email address updated successfully',
            'new_email' => $data['new_email'],
        ], 200);
    }

    /**
     * Search players for favorite player dropdown
     */
    public function searchPlayers(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $query = $request->get_param('q');
        if (strlen($query) < 2) {
            return new \WP_REST_Response([
                'success' => true,
                'players' => [],
            ], 200);
        }

        $searchTerm = '%' . $wpdb->esc_like($query) . '%';

        // Search in wp_bbj_players table
        $players = $wpdb->get_results($wpdb->prepare("
            SELECT
                p.id,
                p.first_name,
                p.last_name,
                p.official_nickname,
                p.profile_picture,
                s.post_title as season_name
            FROM {$wpdb->prefix}bbj_players p
            LEFT JOIN {$wpdb->prefix}bbj_v2_player_season ps ON p.id = ps.bbj_player
            LEFT JOIN {$wpdb->posts} s ON ps.bbj_season = s.ID
            WHERE
                CONCAT(p.first_name, ' ', p.last_name) LIKE %s
                OR p.official_nickname LIKE %s
                OR p.first_name LIKE %s
                OR p.last_name LIKE %s
            GROUP BY p.id
            ORDER BY p.last_name, p.first_name
            LIMIT 20
        ", $searchTerm, $searchTerm, $searchTerm, $searchTerm), ARRAY_A);

        $formatted = array_map(function ($p) {
            $name = trim($p['first_name'] . ' ' . $p['last_name']);
            $nickname = $p['official_nickname'];
            $displayName = $nickname ? "\"{$nickname}\" {$name}" : $name;

            // Get photo URL from attachment ID
            $photoUrl = null;
            if ($p['profile_picture']) {
                $photoUrl = wp_get_attachment_image_url((int) $p['profile_picture'], 'thumbnail');
            }

            return [
                'id' => (int) $p['id'],
                'name' => $name,
                'nickname' => $nickname,
                'display_name' => $displayName,
                'season' => $p['season_name'],
                'photo_url' => $photoUrl,
            ];
        }, $players);

        return new \WP_REST_Response([
            'success' => true,
            'players' => $formatted,
        ], 200);
    }

    /**
     * Get help/FAQ data including rank definitions
     */
    public function getHelpData(\WP_REST_Request $request): \WP_REST_Response
    {
        $ranks = RankCalculator::getAllRanks();

        // Format regular ranks with requirements
        $regularRanks = [];
        foreach ($ranks['regular'] as $key => $rank) {
            $regularRanks[] = [
                'key' => $key,
                'name' => $rank['name'],
                'min_comments' => $rank['min_comments'],
                'min_karma' => $rank['min_karma'],
                'color' => $rank['color'],
                'bg_color' => $rank['bg_color'],
                'icon' => $rank['icon'] ?? null,
            ];
        }

        // Format special ranks
        $specialRanks = [];
        foreach ($ranks['special'] as $key => $rank) {
            $specialRanks[] = [
                'key' => $key,
                'name' => $rank['name'],
                'color' => $rank['color'],
                'bg_color' => $rank['bg_color'],
                'icon' => $rank['icon'] ?? null,
            ];
        }

        return new \WP_REST_Response([
            'success' => true,
            'help' => [
                'ranks' => [
                    'regular' => $regularRanks,
                    'special' => $specialRanks,
                    'explanation' => [
                        'karma' => 'Karma is calculated as: upvotes received - downvotes received on your comments.',
                        'progression' => 'To advance to the next rank, you must meet BOTH the minimum comment count AND minimum karma requirements.',
                        'special' => 'Special ranks are awarded by site administrators and override calculated ranks.',
                    ],
                ],
                'faq' => [
                    [
                        'question' => 'How do I increase my rank?',
                        'answer' => 'Post quality comments that other users upvote. Each rank requires both a minimum number of comments and a minimum karma score.',
                    ],
                    [
                        'question' => 'What is karma?',
                        'answer' => 'Karma is your reputation score based on how other users vote on your comments. Upvotes increase karma, downvotes decrease it.',
                    ],
                    [
                        'question' => 'What are supporter benefits?',
                        'answer' => 'Supporters enjoy an ad-free experience, exclusive badges, priority push notifications for feed updates, quick reply on feed updates, and early access to new features.',
                    ],
                    [
                        'question' => 'What payment methods do you accept?',
                        'answer' => 'We accept all major credit cards via Stripe, and PayPal.',
                    ],
                    [
                        'question' => 'How do I cancel my subscription?',
                        'answer' => 'Go to Settings > Premium and click "Manage Subscription" (for Stripe) or "Cancel Subscription" (for PayPal). You\'ll keep access until the end of your billing period.',
                    ],
                    [
                        'question' => 'Will I get a refund if I cancel?',
                        'answer' => 'Subscriptions are non-refundable, but you\'ll retain access until your current billing period ends. Contact us for special circumstances.',
                    ],
                    [
                        'question' => 'What\'s the difference between plans?',
                        'answer' => 'Monthly ($6.95/mo) and Season Pass ($35/yr) both give Supporter status. Lifetime ($99 one-time) gives permanent access and a special Lifetime badge.',
                    ],
                    [
                        'question' => 'Can I upgrade from Monthly to Lifetime?',
                        'answer' => 'Yes! Just purchase the Lifetime plan and your monthly subscription will be cancelled automatically.',
                    ],
                    [
                        'question' => 'Can I change my username?',
                        'answer' => 'Usernames cannot be changed to prevent confusion in the community. You can update your display name instead.',
                    ],
                    [
                        'question' => 'How do I set a profile picture?',
                        'answer' => 'Click the camera icon on your avatar in the Profile tab to upload a custom photo. Images are cropped to square and converted to WebP for best performance.',
                    ],
                ],
            ],
        ], 200);
    }

    /**
     * Get player by ID
     */
    private function getPlayerById(int $playerId): ?array
    {
        global $wpdb;

        $player = $wpdb->get_row($wpdb->prepare("
            SELECT
                p.id,
                p.first_name,
                p.last_name,
                p.official_nickname,
                p.profile_picture,
                s.post_title as season_name
            FROM {$wpdb->prefix}bbj_players p
            LEFT JOIN {$wpdb->prefix}bbj_v2_player_season ps ON p.id = ps.bbj_player
            LEFT JOIN {$wpdb->posts} s ON ps.bbj_season = s.ID
            WHERE p.id = %d
            LIMIT 1
        ", $playerId), ARRAY_A);

        if (!$player) {
            return null;
        }

        $name = trim($player['first_name'] . ' ' . $player['last_name']);
        $nickname = $player['official_nickname'];

        $photoUrl = null;
        if ($player['profile_picture']) {
            $photoUrl = wp_get_attachment_image_url((int) $player['profile_picture'], 'thumbnail');
        }

        return [
            'id' => (int) $player['id'],
            'name' => $name,
            'nickname' => $nickname,
            'display_name' => $nickname ? "\"{$nickname}\" {$name}" : $name,
            'season' => $player['season_name'],
            'photo_url' => $photoUrl,
        ];
    }

    /**
     * Permission callback: Check if user is logged in
     */
    public function checkUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }
}
