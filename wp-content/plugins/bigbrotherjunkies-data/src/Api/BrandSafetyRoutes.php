<?php

namespace BigBrotherJunkies\Data\Api;

use WP_REST_Request;
use WP_REST_Response;
use BigBrotherJunkies\Data\BrandSafety\Backfill;
use BigBrotherJunkies\Data\BrandSafety\Blocklist;
use BigBrotherJunkies\Data\BrandSafety\Hooks;
use BigBrotherJunkies\Data\BrandSafety\Log;
use BigBrotherJunkies\Data\Comments\CommentSchema;
use BigBrotherJunkies\Data\Comments\MediaUploader;
use BigBrotherJunkies\Data\Permissions\PermissionChecker;

/**
 * Brand-safety admin API: blocklist tier settings, legacy-content backfill
 * (dry-run + batched apply), the flagged-posts review queue, the raw hit
 * log, and the one-shot comment-media quarantine trigger.
 */
class BrandSafetyRoutes
{
    private const NAMESPACE = 'bbjd/v1';

    private const BATCH_DEFAULTS = [
        'posts' => 25,
        'comments' => 500,
        'quotes' => 500,
    ];

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/brand-safety/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getSettings'],
                'permission_callback' => [$this, 'checkAccess'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updateSettings'],
                'permission_callback' => [$this, 'checkAccess'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/brand-safety/dry-run', [
            'methods' => 'POST',
            'callback' => [$this, 'dryRun'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/brand-safety/apply', [
            'methods' => 'POST',
            'callback' => [$this, 'apply'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/brand-safety/flagged', [
            'methods' => 'GET',
            'callback' => [$this, 'flagged'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/brand-safety/override', [
            'methods' => 'POST',
            'callback' => [$this, 'override'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/brand-safety/log', [
            'methods' => 'GET',
            'callback' => [$this, 'log'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/brand-safety/quarantine-media', [
            'methods' => 'POST',
            'callback' => [$this, 'quarantineMedia'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);
    }

    public function getSettings(): WP_REST_Response
    {
        return self::settingsResponse(get_option(Blocklist::OPTION, []));
    }

    public function updateSettings(WP_REST_Request $request): WP_REST_Response
    {
        $overridesInput = $request->get_param('tier_overrides');
        $opt = get_option(Blocklist::OPTION, []);

        if (is_array($overridesInput)) {
            $overrides = $opt['tier_overrides'] ?? [];
            foreach ($overridesInput as $term => $tier) {
                if (in_array($tier, Blocklist::TIERS, true)) {
                    $overrides[strtolower((string) $term)] = $tier;
                }
            }
            $opt['tier_overrides'] = $overrides;
            update_option(Blocklist::OPTION, $opt);
            Blocklist::flushCache();
        }

        return self::settingsResponse($opt);
    }

    /** Shared GET/PUT settings payload, built from an already-read option array. */
    private static function settingsResponse(array $opt): WP_REST_Response
    {
        return new WP_REST_Response([
            'enabled' => (bool) ($opt['enabled'] ?? true),
            'tiers' => Blocklist::getTiers(),
            'watch_defaults' => Blocklist::WATCH_DEFAULTS,
        ], 200);
    }

    /**
     * Time-sliced: a target with a large object count needs multiple calls
     * to finish (each one advances the resumable scan by ~20s of wall time —
     * see Backfill::dryRun). Callers must poll until `done:true`; only the
     * finished response carries the full report/hits fields.
     */
    public function dryRun(WP_REST_Request $request): WP_REST_Response
    {
        $target = (string) $request->get_param('target');
        if (!in_array($target, Backfill::TARGETS, true)) {
            return new WP_REST_Response(['error' => 'invalid target'], 400);
        }

        $result = Backfill::dryRun($target);

        if (!$result['done']) {
            return new WP_REST_Response([
                'target' => $target,
                'done' => false,
                'scanned_so_far' => $result['scanned_so_far'],
                'hits_objects_so_far' => $result['hits_objects_so_far'],
            ], 200);
        }

        $report = Backfill::readReport($target);
        $rows = $report['rows'] ?? [];
        $hitsTotal = 0;
        foreach ($rows as $row) {
            $hitsTotal += count($row['terms']);
        }

        return new WP_REST_Response([
            'target' => $target,
            'done' => true,
            'scanned' => $result['scanned_so_far'],
            'scanned_so_far' => $result['scanned_so_far'],
            'hits_objects' => count($rows),
            'hits_objects_so_far' => $result['hits_objects_so_far'],
            'hits_total' => $hitsTotal,
            'report' => array_slice($rows, 0, 500),
        ], 200);
    }

    public function apply(WP_REST_Request $request): WP_REST_Response
    {
        $target = (string) $request->get_param('target');
        if (!in_array($target, Backfill::TARGETS, true)) {
            return new WP_REST_Response(['error' => 'invalid target'], 400);
        }

        $batchParam = $request->get_param('batch');
        $batch = $batchParam !== null && $batchParam !== '' ? (int) $batchParam : self::BATCH_DEFAULTS[$target];

        // Default false: a purge hits the real revalidate endpoint (prod by
        // default when NEXT_PUBLIC_SITE_URL isn't defined), so callers must
        // opt in explicitly. No-op for comments/quotes (handled in Backfill).
        $purge = (bool) $request->get_param('purge');

        return new WP_REST_Response(Backfill::apply($target, $batch, $purge), 200);
    }

    public function flagged(): WP_REST_Response
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_name, p.post_type
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value = '1'
             ORDER BY p.ID DESC",
            Hooks::META_UNSAFE
        ));

        $out = [];
        foreach ($rows as $r) {
            $url = $r->post_type === 'live-feed-updates' ? "/live-feed-updates/{$r->post_name}" : "/{$r->post_name}";
            $out[] = [
                'id' => (int) $r->ID,
                'title' => $r->post_title,
                'url' => $url,
                'overridden' => get_post_meta((int) $r->ID, Hooks::META_OVERRIDE, true) === '1',
            ];
        }

        return new WP_REST_Response($out, 200);
    }

    public function override(WP_REST_Request $request): WP_REST_Response
    {
        $postId = (int) $request->get_param('post_id');
        $safe = (bool) $request->get_param('safe');

        if (!$postId || !get_post($postId)) {
            return new WP_REST_Response(['error' => 'post not found'], 404);
        }

        if ($safe) {
            update_post_meta($postId, Hooks::META_OVERRIDE, '1');
        } else {
            delete_post_meta($postId, Hooks::META_OVERRIDE);
        }

        return new WP_REST_Response([
            'post_id' => $postId,
            'overridden' => $safe,
            'ads_unsafe' => Hooks::isAdsUnsafe($postId),
        ], 200);
    }

    public function log(WP_REST_Request $request): WP_REST_Response
    {
        $page = max(1, (int) ($request->get_param('page') ?: 1));
        return new WP_REST_Response(Log::recent($page), 200);
    }

    public function quarantineMedia(): WP_REST_Response
    {
        $result = MediaUploader::quarantineAllLocal();

        global $wpdb;
        $table = CommentSchema::table(CommentSchema::TABLE_MEDIA);
        $remaining = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE storage_type = 'local'");

        return new WP_REST_Response(array_merge($result, ['remaining_local' => $remaining]), 200);
    }

    public function checkAccess(): bool
    {
        return PermissionChecker::userCan('brand_safety');
    }
}
