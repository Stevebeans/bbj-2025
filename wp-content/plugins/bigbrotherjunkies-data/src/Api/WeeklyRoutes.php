<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Permissions\PermissionChecker;
use BigBrotherJunkies\Data\Social\WeekSummaryService;
use BigBrotherJunkies\Data\Utils\Revalidation;
use BigBrotherJunkies\Data\Weekly\WeeklyAdmin;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Admin REST routes for the week-by-week editor (/admin/weekly).
 * All routes require the season_management permission. After writes we
 * revalidate ONLY the season page + the changed players (cost guardrail:
 * never archive-wide tags).
 */
class WeeklyRoutes
{
    private const NAMESPACE = 'bbjd/v1';

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/admin/seasons/(?P<id>\d+)/weeks', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getWeeks'],
                'permission_callback' => [$this, 'checkAccess'],
                'args' => ['id' => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint']],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'createWeek'],
                'permission_callback' => [$this, 'checkAccess'],
                'args' => ['id' => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint']],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/weeks/(?P<week_id>\d+)', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'saveWeek'],
                'permission_callback' => [$this, 'checkAccess'],
                'args' => ['week_id' => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint']],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'deleteWeek'],
                'permission_callback' => [$this, 'checkAccess'],
                'args' => ['week_id' => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint']],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/admin/weeks/(?P<week_id>\d+)/summary-draft', [
            'methods' => 'POST',
            'callback' => [$this, 'draftSummary'],
            'permission_callback' => [$this, 'checkAccess'],
            'args' => ['week_id' => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint']],
        ]);
    }

    public function checkAccess(): bool
    {
        return PermissionChecker::userCan('season_management');
    }

    /** id=0 means "current season" (bbj_v2_current_season option). */
    private function resolveSeasonId(int $id): int
    {
        return $id > 0 ? $id : (int) get_option('bbj_v2_current_season', 0);
    }

    public function getWeeks(WP_REST_Request $request): WP_REST_Response
    {
        $seasonId = $this->resolveSeasonId((int) $request->get_param('id'));
        if (!$seasonId) {
            return new WP_REST_Response(['success' => false, 'message' => 'No season selected and no current season configured'], 400);
        }
        $bundle = WeeklyAdmin::getAdminBundle($seasonId);
        $bundle['success'] = true;
        $bundle['season_slug'] = (string) get_post_field('post_name', $seasonId);
        return new WP_REST_Response($bundle, 200);
    }

    public function createWeek(WP_REST_Request $request): WP_REST_Response
    {
        $seasonId = $this->resolveSeasonId((int) $request->get_param('id'));
        if (!$seasonId) {
            return new WP_REST_Response(['success' => false, 'message' => 'No season selected'], 400);
        }
        $params = $request->get_json_params() ?: [];
        $created = WeeklyAdmin::createWeek(
            $seasonId,
            $this->cleanDate($params['start_date'] ?? null),
            $this->cleanDate($params['end_date'] ?? null)
        );
        return new WP_REST_Response(['success' => true] + $created, 201);
    }

    public function saveWeek(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $request->get_json_params() ?: [];
        foreach (['start_date', 'end_date'] as $k) {
            if (array_key_exists($k, $payload)) {
                $payload[$k] = $this->cleanDate($payload[$k]);
            }
        }
        $result = WeeklyAdmin::saveWeek((int) $request->get_param('week_id'), $payload);
        if (!empty($result['errors'])) {
            $status = $result['errors'] === ['Week not found'] ? 404 : 400;
            return new WP_REST_Response(['success' => false, 'errors' => $result['errors']], $status);
        }
        $this->revalidateChanged($result['season_id'], $result['changed_player_ids']);
        return new WP_REST_Response(['success' => true, 'changed' => count($result['changed_player_ids'])], 200);
    }

    /**
     * AI-draft the season-page week summary from the editor's CURRENT form
     * payload (unsaved edits included). Returns text for the textarea only;
     * nothing is saved until the user hits Save Week.
     */
    public function draftSummary(WP_REST_Request $request): WP_REST_Response
    {
        $result = WeekSummaryService::generate(
            (int) $request->get_param('week_id'),
            $request->get_json_params() ?: []
        );
        if (empty($result['success'])) {
            $status = ($result['message'] ?? '') === 'Week not found.' ? 404 : 502;
            return new WP_REST_Response($result, $status);
        }
        return new WP_REST_Response($result, 200);
    }

    public function deleteWeek(WP_REST_Request $request): WP_REST_Response
    {
        $result = WeeklyAdmin::deleteWeek((int) $request->get_param('week_id'));
        if ($result === null) {
            return new WP_REST_Response(['success' => false, 'message' => 'Week not found'], 404);
        }
        $this->revalidateChanged($result['season_id'], $result['changed_player_ids']);
        return new WP_REST_Response(['success' => true], 200);
    }

    /** Accept YYYY-MM-DD or empty; anything else becomes null. */
    private function cleanDate($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return ($d && $d->format('Y-m-d') === $value) ? $value : null;
    }

    /** Targeted revalidation only: season page + each changed player. */
    private function revalidateChanged(int $seasonId, array $playerIds): void
    {
        $seasonSlug = (string) get_post_field('post_name', $seasonId);
        if ($seasonSlug) {
            Revalidation::revalidateSeason($seasonSlug);
        }
        foreach (array_unique(array_map('intval', $playerIds)) as $pid) {
            $slug = (string) get_post_field('post_name', $pid);
            if ($slug) {
                Revalidation::revalidatePlayer($slug);
            }
        }
    }
}
