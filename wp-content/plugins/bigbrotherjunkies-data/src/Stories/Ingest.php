<?php

namespace BigBrotherJunkies\Data\Stories;

use BigBrotherJunkies\Data\Stories\Sources\InstagramAdapter;
use BigBrotherJunkies\Data\Stories\Sources\RedditAdapter;
use BigBrotherJunkies\Data\Stories\Sources\RssAdapter;
use BigBrotherJunkies\Data\Stories\Sources\SourceAdapter;

class Ingest
{
    private const LOCK_KEY = 'bbjd_stories_ingest_lock';

    public static function adapterFor(string $type): ?SourceAdapter
    {
        switch ($type) {
            case 'rss':       return new RssAdapter();
            case 'reddit':    return new RedditAdapter();
            case 'instagram': return new InstagramAdapter();
            // 'bluesky' arrives in Batch B (reads Task 12 rows).
        }
        return null;
    }

    /** One pass over every enabled show/source. Per-source try/catch: one dead feed never stalls the rest. */
    public static function run(): array
    {
        $shows = Settings::enabledShows();
        if (!$shows) {
            return ['skipped' => true, 'ingested' => 0, 'errors' => []];
        }
        if (get_transient(self::LOCK_KEY)) {
            return ['skipped' => true, 'ingested' => 0, 'errors' => ['Another ingest is running.']];
        }
        set_transient(self::LOCK_KEY, 1, 300);
        $runId = ItemStore::recordRun(['show' => '', 'kind' => 'ingest']);
        $ingested = 0;
        $errors   = [];
        try {
            foreach (array_keys($shows) as $slug) {
                foreach (Settings::sourcesFor($slug) as $source) {
                    $adapter = self::adapterFor($source['type']);
                    if (!$adapter) {
                        continue;
                    }
                    try {
                        $res = $adapter->fetch($source);
                        $ingested += ItemStore::insertItems($res['items']);
                        if (!empty($res['error'])) {
                            $errors[] = $res['error'];
                        }
                    } catch (\Throwable $e) {
                        $msg = $source['type'] . ' ' . $source['key'] . ': ' . $e->getMessage();
                        error_log('[BBJ Stories] ingest ' . $msg);
                        $errors[] = $msg;
                    }
                }
            }
            ItemStore::prune(14);
        } finally {
            delete_transient(self::LOCK_KEY);
            ItemStore::finishRun($runId, ['ingested' => $ingested, 'errors' => $errors]);
        }
        return ['skipped' => false, 'ingested' => $ingested, 'errors' => $errors];
    }

    /** Fetch-only preview for the Sources UI. Never inserts. */
    public static function testSource(array $source): array
    {
        $norm = SettingsLogic::normalizeSources([$source]);
        if (!$norm) {
            return ['items' => [], 'error' => 'Invalid source (check type, key, show).'];
        }
        $adapter = self::adapterFor($norm[0]['type']);
        if (!$adapter) {
            return ['items' => [], 'error' => 'No adapter for ' . $norm[0]['type']];
        }
        try {
            $res = $adapter->fetch($norm[0]);
        } catch (\Throwable $e) {
            return ['items' => [], 'error' => $e->getMessage()];
        }
        return ['items' => array_slice($res['items'], 0, 5), 'error' => $res['error']];
    }
}
