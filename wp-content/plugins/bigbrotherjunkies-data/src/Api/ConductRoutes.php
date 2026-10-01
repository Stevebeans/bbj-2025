<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Conduct\ConductLogic;
use BigBrotherJunkies\Data\Conduct\ConductStore;
use BigBrotherJunkies\Data\Messages\DmStore;
use BigBrotherJunkies\Data\Permissions\PermissionChecker;

/**
 * Admin conduct queue: member-grouped reports (comments + DMs) and the
 * enforcement ladder. Gated on the conduct_moderation permission; members
 * who are protected targets are admin-only (user_management).
 */
class ConductRoutes
{
    private const NAMESPACE = 'bbjd/v1';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/admin/conduct/members', [
            'methods'             => 'GET',
            'callback'            => [$this, 'members'],
            'permission_callback' => [$this, 'checkAccess'],
            'args'                => [
                'page'     => ['type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint'],
                'per_page' => ['type' => 'integer', 'default' => 20, 'sanitize_callback' => 'absint'],
            ],
        ]);
        register_rest_route(self::NAMESPACE, '/admin/conduct/members/(?P<user_id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'member'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);
        register_rest_route(self::NAMESPACE, '/admin/conduct/members/(?P<user_id>\d+)/action', [
            'methods'             => 'POST',
            'callback'            => [$this, 'action'],
            'permission_callback' => [$this, 'checkAccess'],
            'args'                => [
                'action'   => ['required' => true, 'type' => 'string',
                               'enum' => ['warning', 'timeout', 'ban', 'lift']],
                'scope'    => ['type' => 'string', 'default' => '',
                               'enum' => ['', 'commenting', 'dm_init', 'both']],
                'duration' => ['type' => 'string', 'default' => '',
                               'enum' => ['', '24h', '72h', '7d', ConductLogic::SEASON]],
                'note'     => ['type' => 'string', 'default' => '',
                               'sanitize_callback' => 'sanitize_textarea_field'],
            ],
        ]);
        // One-click "sit out the rest of the season" from the comment card.
        // Comment mods get this without being handed the whole conduct ladder.
        register_rest_route(self::NAMESPACE, '/admin/conduct/members/(?P<user_id>\d+)/season-timeout', [
            'methods'             => 'POST',
            'callback'            => [$this, 'seasonTimeout'],
            'permission_callback' => [$this, 'checkCommentModAccess'],
        ]);
    }

    public function checkAccess(): bool
    {
        return PermissionChecker::userCan('conduct_moderation');
    }

    public function checkCommentModAccess(): bool
    {
        return PermissionChecker::userCan('comment_moderation') || $this->checkAccess();
    }

    private function canSeeProtected(): bool
    {
        return PermissionChecker::userCan('user_management');
    }

    public function members(\WP_REST_Request $request): \WP_REST_Response
    {
        $page    = max(1, (int) $request->get_param('page'));
        $perPage = min(50, max(1, (int) $request->get_param('per_page')));
        return new \WP_REST_Response(
            ConductStore::membersOverview($page, $perPage, $this->canSeeProtected()),
            200
        );
    }

    public function member(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = (int) $request->get_param('user_id');
        $user = get_userdata($userId);
        if (!$user) {
            return new \WP_REST_Response(['message' => 'User not found'], 404);
        }
        $isProtected = DmStore::isProtectedTarget($userId);
        if ($isProtected && !$this->canSeeProtected()) {
            return new \WP_REST_Response(['message' => 'Reports about moderators are handled by the site admin.'], 403);
        }
        return new \WP_REST_Response([
            'user' => [
                'id'     => $userId,
                'name'   => $user->display_name,
                'login'  => $user->user_login,
                'avatar' => get_avatar_url($userId),
                'roles'  => array_values((array) $user->roles),
            ],
            'protected' => $isProtected,
            'reports'   => ConductStore::memberReports($userId),
            'actions'   => ConductStore::historyFor($userId),
            'active'    => ConductStore::activeRestrictions($userId),
        ], 200);
    }

    public function action(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = (int) $request->get_param('user_id');
        $action = (string) $request->get_param('action');
        if (DmStore::isProtectedTarget($userId) && !$this->canSeeProtected()) {
            return new \WP_REST_Response(['message' => 'Reports about moderators are handled by the site admin.'], 403);
        }
        $moderatorId = get_current_user_id();
        // applyAction/record cap the stored note at 1000 chars, but the DM body
        // builders receive the raw note first; an unbounded note could push the
        // DM body over the 2000-char validBody cap (silent dm_sent=false) while
        // the stored log stays capped, so cap here before either path sees it.
        $note = mb_substr((string) $request->get_param('note'), 0, 1000);
        $result = ConductStore::applyAction(
            $userId,
            $moderatorId,
            $action,
            (string) $request->get_param('scope'),
            (string) $request->get_param('duration'),
            $note
        );
        if (!$result['ok']) {
            return $this->actionFailed($result);
        }
        if ($action !== 'lift') {
            ConductStore::resolveOpenReportsFor($userId, $moderatorId, $action);
        }
        return new \WP_REST_Response([
            'success' => true,
            'dm_sent' => $result['dm_sent'] ?? false,
            'actions' => ConductStore::historyFor($userId),
            'active'  => ConductStore::activeRestrictions($userId),
        ], 200);
    }

    public function seasonTimeout(\WP_REST_Request $request): \WP_REST_Response
    {
        $userId = (int) $request->get_param('user_id');
        if (DmStore::isProtectedTarget($userId) && !$this->canSeeProtected()) {
            return new \WP_REST_Response(['message' => 'Reports about moderators are handled by the site admin.'], 403);
        }
        $moderatorId = get_current_user_id();
        $result = ConductStore::applyAction(
            $userId,
            $moderatorId,
            'timeout',
            'both',
            ConductLogic::SEASON,
            'Season timeout applied from a comment.'
        );
        if (!$result['ok']) {
            return $this->actionFailed($result);
        }
        ConductStore::resolveOpenReportsFor($userId, $moderatorId, 'timeout');
        return new \WP_REST_Response([
            'success'    => true,
            'dm_sent'    => $result['dm_sent'] ?? false,
            'expires_at' => ConductLogic::SEASON_END_UTC,
        ], 200);
    }

    private function actionFailed(array $result): \WP_REST_Response
    {
        $reason = (string) ($result['reason'] ?? 'error');
        $status = ['protected' => 403, 'no_user' => 404][$reason] ?? 400;
        return new \WP_REST_Response(['message' => 'Could not apply action (' . $reason . ').'], $status);
    }
}
