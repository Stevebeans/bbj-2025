<?php

namespace BigBrotherJunkies\Data\Api;

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
        // The action route is added in Task 9.
    }

    public function checkAccess(): bool
    {
        return PermissionChecker::userCan('conduct_moderation');
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
}
