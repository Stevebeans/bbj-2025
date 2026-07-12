<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\FanVotes\FanVoteStore;
use BigBrotherJunkies\Data\Permissions\PermissionChecker;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST routes for fan-favorite ("AFP") voting.
 *
 *  - GET  /fan-votes/tracker  public standings + daily history
 *  - POST /fan-vote           cast/move the logged-in user's single vote
 *  - GET  /fan-vote/mine      the logged-in user's current-season vote
 *  - GET  /fan-vote/ballot    the logged-in user's ranked ballot + vote weight
 *  - POST /fan-vote/ballot    replace the logged-in user's ranked ballot
 *  - POST /fan-vote/note      admin (season_management) mover note for today
 *
 * Write routes bust the tracker caches. No Revalidation / cache-tag calls
 * anywhere — all vote data is client-fetched (cost guardrail).
 */
class FanVoteRoutes
{
    private const NAMESPACE = 'bbjd/v1';

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/fan-votes/tracker', [
            'methods' => 'GET',
            'callback' => [$this, 'getTracker'],
            'permission_callback' => '__return_true',
            'args' => [
                'days' => ['type' => 'integer', 'default' => 30, 'sanitize_callback' => 'absint'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/fan-vote', [
            'methods' => 'POST',
            'callback' => [$this, 'castVote'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route(self::NAMESPACE, '/fan-vote/mine', [
            'methods' => 'GET',
            'callback' => [$this, 'getMine'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        register_rest_route(self::NAMESPACE, '/fan-vote/ballot', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getBallot'],
                'permission_callback' => 'is_user_logged_in',
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'saveBallot'],
                'permission_callback' => 'is_user_logged_in',
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/fan-vote/note', [
            'methods' => 'POST',
            'callback' => [$this, 'saveNote'],
            'permission_callback' => [$this, 'checkNoteAccess'],
        ]);
    }

    public function checkNoteAccess(): bool
    {
        return PermissionChecker::userCan('season_management');
    }

    public function getTracker(WP_REST_Request $request): WP_REST_Response
    {
        $days = (int) $request->get_param('days');
        if ($days <= 0) {
            $days = 30;
        }
        $payload = FanVoteStore::trackerPayload($days);
        if (empty($payload['season_id'])) {
            return new WP_REST_Response(['success' => false, 'message' => 'No current season configured'], 400);
        }
        return new WP_REST_Response($payload, 200);
    }

    public function castVote(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();

        // One vote write per 60s per user.
        $rlKey = 'bbj_fanvote_rl_' . $userId;
        if (get_transient($rlKey)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Please wait a moment before voting again'], 429);
        }

        $params = $request->get_json_params() ?: [];
        $playerId = (int) ($params['player_id'] ?? 0);
        if ($playerId <= 0) {
            return new WP_REST_Response(['success' => false, 'message' => 'player_id is required'], 400);
        }

        $result = FanVoteStore::castVote($userId, $playerId);
        if (!empty($result['errors'])) {
            return new WP_REST_Response([
                'success' => false,
                'errors' => $result['errors'],
                'message' => implode('; ', $result['errors']),
            ], 400);
        }

        set_transient($rlKey, 1, 60);
        FanVoteStore::bustTrackerCaches();

        return new WP_REST_Response(['success' => true, 'player_id' => $result['player_id']], 200);
    }

    public function getMine(WP_REST_Request $request): WP_REST_Response
    {
        $playerId = FanVoteStore::myVote(get_current_user_id());
        return new WP_REST_Response(['success' => true, 'player_id' => $playerId], 200);
    }

    public function getBallot(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();
        return new WP_REST_Response([
            'success' => true,
            'order'   => FanVoteStore::myBallot($userId),
            'weight'  => FanVoteStore::weightFor($userId),
        ], 200);
    }

    public function saveBallot(WP_REST_Request $request): WP_REST_Response
    {
        $userId = get_current_user_id();

        // Drag sessions save in bursts; a short window absorbs accidental spam.
        $rlKey = 'bbj_fanballot_rl_' . $userId;
        if (get_transient($rlKey)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Please wait a moment before saving again'], 429);
        }

        $params = $request->get_json_params() ?: [];
        $order = $params['order'] ?? null;
        if (!is_array($order) || !$order) {
            return new WP_REST_Response(['success' => false, 'message' => 'order must be a non-empty array of player ids'], 400);
        }

        $result = FanVoteStore::saveBallot($userId, $order);
        if (!empty($result['errors'])) {
            return new WP_REST_Response([
                'success' => false,
                'errors' => $result['errors'],
                'message' => implode('; ', $result['errors']),
            ], 400);
        }

        set_transient($rlKey, 1, 5);
        FanVoteStore::bustTrackerCaches();

        return new WP_REST_Response(['success' => true, 'count' => $result['count']], 200);
    }

    public function saveNote(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params() ?: [];
        $playerId = (int) ($params['player_id'] ?? 0);
        if ($playerId <= 0) {
            return new WP_REST_Response(['success' => false, 'message' => 'player_id is required'], 400);
        }

        $ok = FanVoteStore::saveNote($playerId, (string) ($params['note'] ?? ''));
        if (!$ok) {
            return new WP_REST_Response(['success' => false, 'message' => 'No current season configured'], 400);
        }

        FanVoteStore::bustTrackerCaches();
        return new WP_REST_Response(['success' => true], 200);
    }
}
