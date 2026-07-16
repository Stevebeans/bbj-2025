<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Social\BeanBotService;
use BigBrotherJunkies\Data\Social\DraftService;
use BigBrotherJunkies\Data\Social\HouseTranscriptService;
use BigBrotherJunkies\Data\Social\QuoteLogic;
use BigBrotherJunkies\Data\Social\QuoteService;
use BigBrotherJunkies\Data\Social\QuoteStore;
use BigBrotherJunkies\Data\Social\SocialStore;
use BigBrotherJunkies\Data\Social\SummaryService;
use BigBrotherJunkies\Data\Permissions\PermissionChecker;
use BigBrotherJunkies\Data\Utils\Revalidation;
use WP_REST_Request;
use WP_REST_Response;

/**
 * REST routes for the Bluesky Feed-Intel monitor (admin-only).
 *
 *  - GET  /social/config             current settings (api key masked)
 *  - POST /social/config             save settings + reschedule the poller
 *  - POST /social/poll               run one poll now (manual refresh)
 *  - GET  /social/posts              paged collected posts
 *  - GET  /social/summaries          stored summaries/drafts (optional ?kind=)
 *  - POST /social/summarize          run the Claude summarizer over the unsummarized window
 *  - GET  /social/transcript         a Pacific day's collected posts (entity-decoded)
 *  - GET  /social/quotes/candidates  stored quote-candidate batch for a day, if any
 *  - POST /social/quotes/candidates  generate live quote candidates for a day
 *  - GET  /social/quotes             approved quotes for a season or player
 *  - POST /social/quotes             approve/insert a quote
 *  - DELETE /social/quotes/{id}      remove an approved quote
 *  - POST /social/draft              generate a facebook/blog draft over a time window
 *
 * Every route is gated by PermissionChecker::userCan('social_monitor'). The
 * admin page is client-fetched. Only the quote CRUD routes trigger a
 * Revalidation purge, and only for the one season + one player affected.
 */
class SocialRoutes
{
    private const NAMESPACE = 'bbjd/v1';

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        // Poller callback + idempotent init-time scheduling.
        add_action(SocialStore::POLL_HOOK, [SocialStore::class, 'poll']);
        add_action('init', [SocialStore::class, 'ensureScheduled']);
        // Bean Bot cycle callback + idempotent init-time scheduling.
        add_action(BeanBotService::CYCLE_HOOK, [BeanBotService::class, 'cron']);
        add_action('init', [BeanBotService::class, 'ensureScheduled']);
    }

    public function checkAccess(): bool
    {
        return PermissionChecker::userCan('social_monitor');
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/social/config', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getConfig'],
                'permission_callback' => [$this, 'checkAccess'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'saveConfig'],
                'permission_callback' => [$this, 'checkAccess'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/social/poll', [
            'methods'             => 'POST',
            'callback'            => [$this, 'runPoll'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/social/posts', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getPosts'],
            'permission_callback' => [$this, 'checkAccess'],
            'args'                => [
                'source'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'search'   => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'page'     => ['type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint'],
                'per_page' => ['type' => 'integer', 'default' => 50, 'sanitize_callback' => 'absint'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/social/summaries', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getSummaries'],
            'permission_callback' => [$this, 'checkAccess'],
            'args'                => [
                'kind' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/social/summarize', [
            'methods'             => 'POST',
            'callback'            => [$this, 'runSummarize'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/social/transcript', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getTranscript'],
            'permission_callback' => [$this, 'checkAccess'],
            'args'                => [
                'date' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);
        register_rest_route(self::NAMESPACE, '/social/house-transcript', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getHouseTranscript'],
                'permission_callback' => [$this, 'checkAccess'],
                'args'                => ['date' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field']],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'generateHouseTranscript'],
                'permission_callback' => [$this, 'checkAccess'],
            ],
        ]);
        register_rest_route(self::NAMESPACE, '/social/quotes/candidates', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getQuoteCandidates'],
                'permission_callback' => [$this, 'checkAccess'],
                'args'                => ['date' => ['type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field']],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'generateQuoteCandidates'],
                'permission_callback' => [$this, 'checkAccess'],
            ],
        ]);
        register_rest_route(self::NAMESPACE, '/social/quotes', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getQuotes'],
                'permission_callback' => [$this, 'checkAccess'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'createQuote'],
                'permission_callback' => [$this, 'checkAccess'],
            ],
        ]);
        register_rest_route(self::NAMESPACE, '/social/quotes/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'deleteQuote'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/social/draft', [
            'methods'             => 'POST',
            'callback'            => [$this, 'runDraft'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/social/draft-batch', [
            'methods'             => 'POST',
            'callback'            => [$this, 'runDraftBatch'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);

        register_rest_route(self::NAMESPACE, '/social/beanbot', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'getBeanBot'],
                'permission_callback' => [$this, 'checkAccess'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'saveBeanBot'],
                'permission_callback' => [$this, 'checkAccess'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/social/beanbot/preview', [
            'methods'             => 'POST',
            'callback'            => [$this, 'previewBeanBot'],
            'permission_callback' => [$this, 'checkAccess'],
        ]);
    }

    public function getConfig(): WP_REST_Response
    {
        return new WP_REST_Response([
            'success'  => true,
            'settings' => SocialStore::settingsForApi(),
            'current_season' => (function () {
                $id = (int) get_option('bbj_v2_current_season', 0);
                $post = $id > 0 ? get_post($id) : null;
                if (!$post) {
                    return null;
                }
                // Roster rides the config so the admin card never needs the
                // public season endpoint (CORS-blocked for browser fetches).
                $players = [];
                if (function_exists('bbj_v2_get_season_players')) {
                    foreach ((array) bbj_v2_get_season_players($id, 'thumbnail') as $p) {
                        $players[] = [
                            // The quote flow keys players by WP POST id — that's
                            // bbj_player from the season-links join (same field
                            // SeasonRoutes::formatPlayer exposes as `id`).
                            'id'       => (int) ($p['bbj_player'] ?? 0),
                            'name'     => trim(trim((string) ($p['first_name'] ?? '')) . ' ' . trim((string) ($p['last_name'] ?? ''))),
                            'nickname' => (string) ($p['official_nickname'] ?? ''),
                        ];
                    }
                }
                return ['id' => $id, 'slug' => $post->post_name, 'players' => $players];
            })(),
        ], 200);
    }

    public function saveConfig(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params() ?: [];
        SocialStore::saveSettings($params);
        SocialStore::reschedule();

        return new WP_REST_Response([
            'success'  => true,
            'settings' => SocialStore::settingsForApi(),
        ], 200);
    }

    public function runPoll(): WP_REST_Response
    {
        $result = SocialStore::poll();
        return new WP_REST_Response(array_merge(['success' => true], $result), 200);
    }

    public function getPosts(WP_REST_Request $request): WP_REST_Response
    {
        $result = SocialStore::posts([
            'source'   => $request->get_param('source'),
            'search'   => $request->get_param('search'),
            'page'     => (int) $request->get_param('page'),
            'per_page' => (int) $request->get_param('per_page'),
        ]);

        return new WP_REST_Response(array_merge(['success' => true], $result), 200);
    }

    public function getSummaries(WP_REST_Request $request): WP_REST_Response
    {
        // Optional ?kind= filter, whitelisted to the three known kinds.
        $kind = (string) $request->get_param('kind');
        $kind = in_array($kind, ['digest', 'facebook', 'blog'], true) ? $kind : null;

        // Stored content is wp_encode_emoji'd (utf8mb3-safe); decode it here so
        // GET matches what POST /social/summarize already returns, otherwise a
        // page reload shows literal entities like &#x1f5a4; in the digest.
        $summaries = array_map(static function (array $row): array {
            $row['content'] = html_entity_decode((string) ($row['content'] ?? ''), ENT_QUOTES, 'UTF-8');
            return $row;
        }, SocialStore::summaries(20, $kind));

        return new WP_REST_Response([
            'success'   => true,
            'summaries' => $summaries,
        ], 200);
    }

    public function runSummarize(): WP_REST_Response
    {
        $result = SummaryService::run();
        $status = !empty($result['success']) ? 200 : 400;
        return new WP_REST_Response($result, $status);
    }

    public function runDraft(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params() ?: [];
        $kind   = is_string($params['kind'] ?? null) ? $params['kind'] : '';
        $window = is_string($params['window'] ?? null) ? $params['window'] : 'today';

        $result = DraftService::generate($kind, $window);
        $status = !empty($result['success']) ? 200 : 400;
        return new WP_REST_Response($result, $status);
    }

    /** Batch mode: 1-5 queueable FB posts since the last batch cursor. */
    public function runDraftBatch(): WP_REST_Response
    {
        $result = DraftService::generateBatch();
        $status = !empty($result['success']) ? 200 : 400;
        return new WP_REST_Response($result, $status);
    }

    // -----------------------------------------------------------------------
    // Quotes (transcript, candidates, CRUD)
    // -----------------------------------------------------------------------

    public function getTranscript(WP_REST_Request $request): WP_REST_Response
    {
        $date = (string) $request->get_param('date');
        if (!self::isValidDate($date)) {
            return new WP_REST_Response(['message' => 'Invalid date.'], 400);
        }
        $bounds = QuoteLogic::houseDayBounds($date);
        $posts  = SocialStore::postsForDay($bounds['from'], $bounds['to']);
        foreach ($posts as &$p) {
            $p['text'] = html_entity_decode((string) $p['text'], ENT_QUOTES, 'UTF-8');
            // display_name is wp_encode_emoji'd at rest too. Undecoded, an
            // emoji-heavy name becomes one huge unbroken &#x...; entity string
            // that blows the nowrap handle column out to ~900px and shoves the
            // text column off-screen (Kali's zodiac garland, 2026-07-14).
            $p['display_name'] = html_entity_decode((string) $p['display_name'], ENT_QUOTES, 'UTF-8');
        }
        unset($p);
        return new WP_REST_Response(['date' => $date, 'posts' => $posts], 200);
    }

    public function getHouseTranscript(WP_REST_Request $request): WP_REST_Response
    {
        $date = (string) $request->get_param('date');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return new WP_REST_Response(['message' => 'Invalid date.'], 400);
        }
        $stored = HouseTranscriptService::storedForDay($date);
        return new WP_REST_Response($stored ?? ['date' => $date, 'lines' => null], 200);
    }

    public function generateHouseTranscript(WP_REST_Request $request): WP_REST_Response
    {
        $date = (string) $request->get_param('date');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return new WP_REST_Response(['message' => 'Invalid date.'], 400);
        }
        $out = HouseTranscriptService::generate($date);
        return new WP_REST_Response($out, empty($out['success']) ? 502 : 200);
    }

    public function getQuoteCandidates(WP_REST_Request $request): WP_REST_Response
    {
        $date = (string) $request->get_param('date');
        if (!self::isValidDate($date)) {
            return new WP_REST_Response(['message' => 'Invalid date.'], 400);
        }
        $stored = QuoteService::storedCandidates($date);
        return new WP_REST_Response($stored ?? ['date' => $date, 'candidates' => null], 200);
    }

    public function generateQuoteCandidates(WP_REST_Request $request): WP_REST_Response
    {
        $date = (string) $request->get_param('date');
        if (!self::isValidDate($date)) {
            return new WP_REST_Response(['message' => 'Invalid date.'], 400);
        }
        $out = QuoteService::candidates($date);
        return new WP_REST_Response($out, empty($out['success']) ? 502 : 200);
    }

    public function getQuotes(WP_REST_Request $request): WP_REST_Response
    {
        $player = (int) $request->get_param('player');
        $season = (int) $request->get_param('season');
        if ($player > 0) {
            return new WP_REST_Response(['quotes' => QuoteStore::forPlayer($player)], 200);
        }
        if ($season <= 0) {
            $season = (int) get_option('bbj_v2_current_season', 0);
        }
        return new WP_REST_Response(['quotes' => QuoteStore::forSeason($season)], 200);
    }

    public function createQuote(WP_REST_Request $request): WP_REST_Response
    {
        $quote = trim((string) $request->get_param('quote_text'));
        $saidOn = (string) $request->get_param('said_on');
        $season = (int) ($request->get_param('season_id') ?: get_option('bbj_v2_current_season', 0));
        if ($quote === '' || $season <= 0 || !self::isValidDate($saidOn)) {
            return new WP_REST_Response(['message' => 'quote_text, valid said_on and a season are required.'], 400);
        }
        $id = QuoteStore::insert([
            'season_id'    => $season,
            'player_id'    => (int) $request->get_param('player_id'),
            'quote_text'   => $quote,
            'context'      => (string) $request->get_param('context'),
            'said_on'      => $saidOn,
            'source_count' => (int) ($request->get_param('source_count') ?: 1),
        ]);
        if (!$id) {
            return new WP_REST_Response(['message' => 'Insert failed.'], 500);
        }
        $this->purgeQuoteSurfaces($season, (int) $request->get_param('player_id'));
        return new WP_REST_Response(['success' => true, 'id' => $id], 200);
    }

    public function deleteQuote(WP_REST_Request $request): WP_REST_Response
    {
        $row = QuoteStore::get((int) $request->get_param('id'));
        if (!$row) {
            return new WP_REST_Response(['message' => 'Not found.'], 404);
        }
        QuoteStore::delete($row['id']);
        $this->purgeQuoteSurfaces($row['season_id'], (int) ($row['player_id'] ?? 0));
        return new WP_REST_Response(['success' => true], 200);
    }

    /** Shared YYYY-MM-DD guard for the transcript/candidates/quote-create routes. */
    private static function isValidDate(string $date): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }

    /** Targeted purge: exactly the season page + (optionally) one player page. */
    private function purgeQuoteSurfaces(int $seasonId, int $playerId): void
    {
        $season = $seasonId > 0 ? get_post($seasonId) : null;
        if ($season) {
            Revalidation::revalidateSeason($season->post_name);
        }
        $player = $playerId > 0 ? get_post($playerId) : null;
        if ($player) {
            Revalidation::revalidatePlayer($player->post_name);
        }
    }

    // -----------------------------------------------------------------------
    // Bean Bot (auto feed-update writer)
    // -----------------------------------------------------------------------

    public function getBeanBot(): WP_REST_Response
    {
        // The whole option is safe to return; it holds no secrets (the API key
        // lives in the monitor settings, never here).
        return new WP_REST_Response([
            'success'  => true,
            'settings' => BeanBotService::settings(),
        ], 200);
    }

    public function saveBeanBot(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params() ?: [];
        BeanBotService::saveSettings($params);
        BeanBotService::reschedule();

        return new WP_REST_Response([
            'success'  => true,
            'settings' => BeanBotService::settings(),
        ], 200);
    }

    public function previewBeanBot(): WP_REST_Response
    {
        // Dry run: performs the real Claude call, persists nothing. Always 200
        // so the UI can render the (possibly error-shaped) result inline.
        return new WP_REST_Response(BeanBotService::runCycle(true), 200);
    }
}
