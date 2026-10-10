<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Permissions\PermissionChecker;
use BigBrotherJunkies\Data\Utils\Revalidation;

/**
 * Homepage mode toggle: 'season' (the BB layout) or 'offseason' (multi-show
 * front page). Web UI only (/admin/seasons) - Cloudways wp-cli option writes
 * land in a different cache view and read back as ghost data.
 */
class HomepageModeRoutes
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('bbjd/v1', '/admin/homepage-mode', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get'],
                'permission_callback' => [$this, 'checkAccess'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'save'],
                'permission_callback' => [$this, 'checkAccess'],
                'args' => [
                    'mode' => [
                        'required' => true,
                        'type' => 'string',
                        'enum' => HomeRoutes::HOMEPAGE_MODES,
                    ],
                ],
            ],
        ]);
    }

    public function checkAccess(): bool
    {
        return PermissionChecker::userCan('season_management');
    }

    public function get(): \WP_REST_Response
    {
        return new \WP_REST_Response(['mode' => HomeRoutes::homepageMode()]);
    }

    public function save(\WP_REST_Request $request): \WP_REST_Response
    {
        $mode = (string) $request->get_param('mode');
        update_option(HomeRoutes::HOMEPAGE_MODE_OPTION, $mode, true);
        delete_transient(HomeRoutes::HOMEPAGE_TRANSIENT);

        // Targeted: revalidates '/' and purges only the homepage URL from CF.
        $revalidated = Revalidation::revalidateHomepage();

        return new \WP_REST_Response(['mode' => HomeRoutes::homepageMode(), 'revalidated' => $revalidated]);
    }
}
