<?php

namespace BigBrotherJunkies\Data\Api;

use BigBrotherJunkies\Data\Permissions\PermissionChecker;
use BigBrotherJunkies\Data\Stories\Ingest;
use BigBrotherJunkies\Data\Stories\ItemStore;
use BigBrotherJunkies\Data\Stories\QuickieHandoff;
use BigBrotherJunkies\Data\Stories\Scheduler;
use BigBrotherJunkies\Data\Stories\Settings;
use BigBrotherJunkies\Data\Stories\Sources\InstagramAdapter;
use WP_REST_Request;
use WP_REST_Response;

/** /bbjd/v1/stories/* — Stories subsystem (Batch A: settings, sources, inbox, ingest, runs). */
class StoriesRoutes
{
    private const NS = 'bbjd/v1';

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action(Scheduler::INGEST_HOOK, [Scheduler::class, 'cronIngest']);
        add_action('init', [Scheduler::class, 'ensureScheduled']);
    }

    public function checkAccess(): bool
    {
        return PermissionChecker::userCan('stories_manage');
    }

    public function registerRoutes(): void
    {
        $r = fn(string $path, string $method, string $cb) => register_rest_route(self::NS, $path, [
            'methods'             => $method,
            'callback'            => [$this, $cb],
            'permission_callback' => [$this, 'checkAccess'],
        ]);
        $r('/stories/settings', 'GET', 'getSettings');
        $r('/stories/settings', 'POST', 'saveSettings');
        $r('/stories/settings/resolve-ig', 'POST', 'resolveIg');
        $r('/stories/sources/test', 'POST', 'testSource');
        $r('/stories/inbox', 'GET', 'inbox');
        $r('/stories/inbox/(?P<id>\d+)/prefill', 'GET', 'prefill');
        $r('/stories/inbox/(?P<id>\d+)/quickie', 'POST', 'quickie');
        $r('/stories/inbox/(?P<id>\d+)/dismiss', 'POST', 'dismiss');
        $r('/stories/inbox/(?P<id>\d+)/restore', 'POST', 'restore');
        $r('/stories/ingest', 'POST', 'ingest');
        $r('/stories/runs', 'GET', 'runs');
    }

    public function getSettings(): WP_REST_Response
    {
        return new WP_REST_Response(['success' => true, 'settings' => Settings::settings()], 200);
    }

    public function saveSettings(WP_REST_Request $req): WP_REST_Response
    {
        $patch = $req->get_json_params() ?: [];
        return new WP_REST_Response(['success' => true, 'settings' => Settings::saveSettings($patch)], 200);
    }

    public function resolveIg(): WP_REST_Response
    {
        [$pageId, $token] = InstagramAdapter::pageCredentials();
        if ($pageId === '' || $token === '') {
            return new WP_REST_Response(['success' => false, 'message' => 'No Facebook page/token in content engine settings.'], 400);
        }
        $res = InstagramAdapter::resolveIgUserId($pageId, $token);
        if ($res['id'] === null) {
            return new WP_REST_Response(['success' => false, 'message' => $res['error']], 400);
        }
        Settings::saveSettings(['ig_user_id' => $res['id']]);
        return new WP_REST_Response(['success' => true, 'ig_user_id' => $res['id']], 200);
    }

    public function testSource(WP_REST_Request $req): WP_REST_Response
    {
        $res = Ingest::testSource($req->get_json_params() ?: []);
        return new WP_REST_Response(['success' => true] + $res, 200);
    }

    public function inbox(WP_REST_Request $req): WP_REST_Response
    {
        $res = ItemStore::inbox([
            'show'     => (string) $req->get_param('show'),
            'source'   => (string) $req->get_param('source'),
            'status'   => (string) ($req->get_param('status') ?: 'new'),
            'sort'     => (string) ($req->get_param('sort') ?: 'score'),
            'page'     => (int) ($req->get_param('page') ?: 1),
            'per_page' => (int) ($req->get_param('per_page') ?: 40),
            'days'     => (int) ($req->get_param('days') ?: 7),
        ]);
        return new WP_REST_Response(['success' => true] + $res, 200);
    }

    public function prefill(WP_REST_Request $req): WP_REST_Response
    {
        $res = QuickieHandoff::prefill((int) $req->get_param('id'));
        return new WP_REST_Response($res, empty($res['success']) ? 404 : 200);
    }

    public function quickie(WP_REST_Request $req): WP_REST_Response
    {
        $p   = $req->get_json_params() ?: [];
        $res = QuickieHandoff::enqueue((int) $req->get_param('id'), [
            'page_id'      => (string) ($p['page_id'] ?? ''),
            'page_name'    => (string) ($p['page_name'] ?? ''),
            'caption'      => (string) ($p['caption'] ?? ''),
            'scheduled_at' => (string) ($p['scheduled_at'] ?? ''),
        ]);
        return new WP_REST_Response($res, empty($res['success']) ? 400 : 200);
    }

    public function dismiss(WP_REST_Request $req): WP_REST_Response
    {
        ItemStore::setInboxStatus((int) $req->get_param('id'), 'dismissed');
        return new WP_REST_Response(['success' => true], 200);
    }

    public function restore(WP_REST_Request $req): WP_REST_Response
    {
        ItemStore::setInboxStatus((int) $req->get_param('id'), 'new');
        return new WP_REST_Response(['success' => true], 200);
    }

    public function ingest(): WP_REST_Response
    {
        return new WP_REST_Response(['success' => true] + Ingest::run(), 200);
    }

    public function runs(): WP_REST_Response
    {
        return new WP_REST_Response(['success' => true, 'runs' => ItemStore::runs(40)], 200);
    }
}
