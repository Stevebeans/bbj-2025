<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Messages\DmStore;
use BigBrotherJunkies\Data\Permissions\PermissionChecker;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Direct-message REST routes. All client-fetched, all logged-in-only.
 * Human-readable refusal copy lives here; DmStore returns machine reasons.
 */
class DmRoutes
{
    private const NAMESPACE = 'bbjd/v1';

    private const REASON_COPY = [
        'blocked'        => 'You cannot message this member.',
        'closed'         => 'This member is not accepting messages.',
        'not_followed'   => 'This member only accepts messages from people they follow.',
        'self'           => 'You cannot message yourself.',
        'no_user'        => 'That member does not exist.',
        'empty'          => 'Message cannot be empty.',
        'too_long'       => 'Message is too long (2,000 characters max).',
        'rate_limited'   => 'You are sending messages too fast. Give it a minute.',
        'thread_limited' => 'You have started too many new conversations today.',
        'db'             => 'Could not save the message. Please try again.',
        'suspended'      => "You can't send messages right now.",
    ];

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $auth = 'is_user_logged_in';

        register_rest_route(self::NAMESPACE, '/dm/threads', [
            'methods' => 'GET', 'callback' => [$this, 'threads'], 'permission_callback' => $auth,
        ]);
        register_rest_route(self::NAMESPACE, '/dm/threads/(?P<id>\d+)/messages', [
            'methods' => 'GET', 'callback' => [$this, 'messages'], 'permission_callback' => $auth,
        ]);
        register_rest_route(self::NAMESPACE, '/dm/threads/(?P<id>\d+)/read', [
            'methods' => 'POST', 'callback' => [$this, 'markRead'], 'permission_callback' => $auth,
        ]);
        register_rest_route(self::NAMESPACE, '/dm/send', [
            'methods' => 'POST', 'callback' => [$this, 'send'], 'permission_callback' => $auth,
        ]);
        register_rest_route(self::NAMESPACE, '/dm/unread-count', [
            'methods' => 'GET', 'callback' => [$this, 'unreadCount'], 'permission_callback' => $auth,
        ]);
        register_rest_route(self::NAMESPACE, '/dm/blocks', [
            ['methods' => 'GET',    'callback' => [$this, 'blockList'], 'permission_callback' => $auth],
            ['methods' => 'POST',   'callback' => [$this, 'block'],     'permission_callback' => $auth],
            ['methods' => 'DELETE', 'callback' => [$this, 'unblock'],   'permission_callback' => $auth],
        ]);
        register_rest_route(self::NAMESPACE, '/dm/report', [
            'methods' => 'POST', 'callback' => [$this, 'report'], 'permission_callback' => $auth,
        ]);
        register_rest_route(self::NAMESPACE, '/dm/privacy', [
            ['methods' => 'GET',  'callback' => [$this, 'getPrivacy'],  'permission_callback' => $auth],
            ['methods' => 'POST', 'callback' => [$this, 'savePrivacy'], 'permission_callback' => $auth],
        ]);

        // Social graph: comment mutes + combined filter lists (comment mute/block spec).
        register_rest_route(self::NAMESPACE, '/social/mute', [
            ['methods' => 'POST',   'callback' => [$this, 'mute'],   'permission_callback' => $auth],
            ['methods' => 'DELETE', 'callback' => [$this, 'unmute'], 'permission_callback' => $auth],
        ]);
        register_rest_route(self::NAMESPACE, '/social/mutes', [
            'methods' => 'GET', 'callback' => [$this, 'muteList'], 'permission_callback' => $auth,
        ]);
        register_rest_route(self::NAMESPACE, '/social/filters', [
            'methods' => 'GET', 'callback' => [$this, 'filters'], 'permission_callback' => $auth,
        ]);

        // Moderation (admin Users tab), gated like the rest of that tab.
        register_rest_route(self::NAMESPACE, '/dm/reports', [
            'methods' => 'GET',
            'callback' => [$this, 'adminReports'],
            'permission_callback' => [$this, 'checkModAccess'],
        ]);
        register_rest_route(self::NAMESPACE, '/dm/reports/(?P<id>\d+)/resolve', [
            'methods' => 'POST',
            'callback' => [$this, 'adminResolveReport'],
            'permission_callback' => [$this, 'checkModAccess'],
        ]);
    }

    public function checkModAccess(): bool
    {
        return PermissionChecker::userCan('user_management');
    }

    public function adminReports(WP_REST_Request $request): WP_REST_Response
    {
        $openOnly = $request->get_param('status') !== 'all';
        $page     = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage  = min(50, max(1, (int) ($request->get_param('per_page') ?? 20)));
        return new WP_REST_Response([
            'success' => true,
            'reports' => DmStore::reportsList($openOnly, $page, $perPage),
        ], 200);
    }

    public function adminResolveReport(WP_REST_Request $request): WP_REST_Response
    {
        $ok = DmStore::resolveReport((int) $request->get_param('id'));
        return new WP_REST_Response(['success' => $ok], $ok ? 200 : 404);
    }

    public function threads(WP_REST_Request $request): WP_REST_Response
    {
        $page    = max(1, (int) ($request->get_param('page') ?? 1));
        $perPage = min(50, max(1, (int) ($request->get_param('per_page') ?? 20)));
        return new WP_REST_Response([
            'success' => true,
            'threads' => DmStore::inbox(get_current_user_id(), $page, $perPage),
        ], 200);
    }

    public function messages(WP_REST_Request $request): WP_REST_Response
    {
        $beforeId = $request->get_param('before_id') ? (int) $request->get_param('before_id') : null;
        $limit    = min(100, max(1, (int) ($request->get_param('limit') ?? 50)));
        $rows = DmStore::messages((int) $request->get_param('id'), get_current_user_id(), $beforeId, $limit);
        if ($rows === null) {
            return new WP_REST_Response(['success' => false, 'message' => 'Not your conversation.'], 403);
        }
        return new WP_REST_Response(['success' => true, 'messages' => $rows], 200);
    }

    public function markRead(WP_REST_Request $request): WP_REST_Response
    {
        $ok = DmStore::markRead((int) $request->get_param('id'), get_current_user_id());
        return new WP_REST_Response(['success' => $ok], $ok ? 200 : 403);
    }

    public function send(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params() ?: [];
        $result = DmStore::send(
            get_current_user_id(),
            (int) ($params['recipient_id'] ?? 0),
            (string) ($params['body'] ?? '')
        );
        if (!$result['ok']) {
            $reason = (string) $result['reason'];
            $status = in_array($reason, ['rate_limited', 'thread_limited'], true) ? 429
                : (in_array($reason, ['blocked', 'closed', 'not_followed', 'suspended'], true) ? 403 : 400);
            return new WP_REST_Response([
                'success' => false,
                'reason'  => $reason,
                'message' => self::REASON_COPY[$reason] ?? 'Could not send.',
            ], $status);
        }
        return new WP_REST_Response([
            'success' => true, 'thread_id' => $result['thread_id'], 'message' => $result['message'],
        ], 200);
    }

    public function unreadCount(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(['count' => DmStore::unreadCount(get_current_user_id())], 200);
    }

    public function blockList(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(['blocked' => DmStore::blockedList(get_current_user_id())], 200);
    }

    public function block(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params() ?: [];
        $target = (int) ($params['user_id'] ?? 0);
        if ($target <= 0 || $target === get_current_user_id()) {
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid user.'], 400);
        }
        if (DmStore::isProtectedTarget($target)) {
            return new WP_REST_Response(['success' => false, 'message' => "Site moderators can't be blocked."], 400);
        }
        DmStore::block(get_current_user_id(), $target);
        return new WP_REST_Response(['success' => true], 200);
    }

    public function unblock(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params() ?: [];
        DmStore::unblock(get_current_user_id(), (int) ($params['user_id'] ?? 0));
        return new WP_REST_Response(['success' => true], 200);
    }

    public function report(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params() ?: [];
        $threadId = (int) ($params['thread_id'] ?? 0);
        if ($threadId <= 0) {
            return new WP_REST_Response(['success' => false, 'message' => 'thread_id is required.'], 400);
        }
        // Reporter must be a participant: reuse the membership check in messages().
        if (DmStore::messages($threadId, get_current_user_id(), null, 1) === null) {
            return new WP_REST_Response(['success' => false, 'message' => 'Not your conversation.'], 403);
        }
        $messageId = isset($params['message_id']) ? (int) $params['message_id'] : null;
        if ($messageId && !DmStore::messageBelongsToThread($messageId, $threadId)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid message.'], 400);
        }
        $reason = (string) ($params['reason'] ?? 'other');
        $ok = DmStore::report(
            get_current_user_id(),
            $threadId,
            $messageId,
            (string) ($params['note'] ?? ''),
            $reason
        );
        return new WP_REST_Response(['success' => $ok], $ok ? 200 : 500);
    }

    public function getPrivacy(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(['privacy' => DmStore::privacyFor(get_current_user_id())], 200);
    }

    public function savePrivacy(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params() ?: [];
        $saved = DmStore::savePrivacy(get_current_user_id(), (string) ($params['privacy'] ?? ''));
        return new WP_REST_Response(['success' => true, 'privacy' => $saved], 200);
    }

    public function mute(WP_REST_Request $request): WP_REST_Response
    {
        $me     = get_current_user_id();
        $params = $request->get_json_params() ?: [];
        $target = (int) ($params['user_id'] ?? 0);
        if ($target <= 0 || $target === $me || !get_userdata($target)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid member.'], 400);
        }
        if (DmStore::isProtectedTarget($target)) {
            return new WP_REST_Response(['success' => false, 'message' => "Site moderators can't be muted."], 400);
        }
        DmStore::mute($me, $target);
        return new WP_REST_Response(['success' => true], 200);
    }

    public function unmute(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params() ?: [];
        DmStore::unmute(get_current_user_id(), (int) ($params['user_id'] ?? 0));
        return new WP_REST_Response(['success' => true], 200);
    }

    public function muteList(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'muted'   => DmStore::mutedList(get_current_user_id()),
        ], 200);
    }

    public function filters(WP_REST_Request $request): WP_REST_Response
    {
        $me = get_current_user_id();
        return new WP_REST_Response([
            'success'     => true,
            'muted_ids'   => DmStore::mutedIds($me),
            'blocked_ids' => DmStore::blockedIds($me),
        ], 200);
    }
}
