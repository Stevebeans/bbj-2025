<?php

namespace BigBrotherJunkies\Tests\Social;

use BigBrotherJunkies\Data\Social\InsightsLogic;
use PHPUnit\Framework\TestCase;

class InsightsLogicTest extends TestCase
{
    // --- cacheTtl -----------------------------------------------------------

    public function test_ttl_same_day_is_short(): void
    {
        $this->assertSame(1800, InsightsLogic::cacheTtl('2026-07-25', '2026-07-25'));
    }

    public function test_ttl_three_days_old_is_still_short(): void
    {
        // Exactly 3 days back is "within 3 days" -> 30 min.
        $this->assertSame(1800, InsightsLogic::cacheTtl('2026-07-22', '2026-07-25'));
    }

    public function test_ttl_four_days_old_is_long(): void
    {
        // 4 days back crosses the boundary -> 7 days.
        $this->assertSame(604800, InsightsLogic::cacheTtl('2026-07-21', '2026-07-25'));
    }

    public function test_ttl_far_past_is_long(): void
    {
        $this->assertSame(604800, InsightsLogic::cacheTtl('2026-01-01', '2026-07-25'));
    }

    // --- mergePosts ---------------------------------------------------------

    public function test_merge_converts_fb_iso8601_to_utc_mysql(): void
    {
        $out = InsightsLogic::mergePosts([], [
            ['id' => 'p1', 'message' => 'hi', 'created_time' => '2026-07-25T14:00:00+0000', 'permalink_url' => 'https://fb/p1'],
        ]);
        $this->assertCount(1, $out);
        $this->assertSame('2026-07-25 14:00:00', $out[0]['posted_at']);
        $this->assertSame('manual_fb', $out[0]['source']);
        $this->assertSame('https://fb/p1', $out[0]['permalink']);
        $this->assertSame('hi', $out[0]['body']);
        $this->assertSame('p1', $out[0]['fb_post_id']);
    }

    public function test_merge_converts_offset_timezone_to_utc(): void
    {
        // +0400 means 14:00 local == 10:00 UTC.
        $out = InsightsLogic::mergePosts([], [
            ['id' => 'p1', 'message' => 'x', 'created_time' => '2026-07-25T14:00:00+0400', 'permalink_url' => ''],
        ]);
        $this->assertSame('2026-07-25 10:00:00', $out[0]['posted_at']);
    }

    public function test_merge_queue_row_wins_but_takes_fb_permalink(): void
    {
        $out = InsightsLogic::mergePosts(
            [['fb_post_id' => 'p1', 'body' => 'queue body', 'posted_at' => '2026-07-25 14:00:00', 'source' => 'social_quickie']],
            [['id' => 'p1', 'message' => 'fb body', 'created_time' => '2026-07-25T14:00:00+0000', 'permalink_url' => 'https://fb/p1']]
        );
        $this->assertCount(1, $out);
        $this->assertSame('queue body', $out[0]['body']);
        $this->assertSame('social_quickie', $out[0]['source']);
        $this->assertSame('https://fb/p1', $out[0]['permalink']);
    }

    public function test_merge_dedupes_and_sorts_ascending(): void
    {
        $out = InsightsLogic::mergePosts(
            [
                ['fb_post_id' => 'later', 'body' => 'b', 'posted_at' => '2026-07-25 20:00:00', 'source' => 'batch'],
            ],
            [
                ['id' => 'earlier', 'message' => 'a', 'created_time' => '2026-07-25T08:00:00+0000', 'permalink_url' => ''],
                ['id' => 'later', 'message' => 'dupe', 'created_time' => '2026-07-25T20:00:00+0000', 'permalink_url' => 'https://fb/later'],
            ]
        );
        $this->assertCount(2, $out);
        $this->assertSame('earlier', $out[0]['fb_post_id']);
        $this->assertSame('later', $out[1]['fb_post_id']);
        // queue row for 'later' wins the body, still gets the fb permalink
        $this->assertSame('b', $out[1]['body']);
        $this->assertSame('https://fb/later', $out[1]['permalink']);
    }

    public function test_merge_manual_fb_only_has_null_permalink_when_absent(): void
    {
        $out = InsightsLogic::mergePosts([], [
            ['id' => 'p1', 'message' => 'x', 'created_time' => '2026-07-25T14:00:00+0000'],
        ]);
        $this->assertNull($out[0]['permalink']);
    }

    public function test_merge_empty_inputs(): void
    {
        $this->assertSame([], InsightsLogic::mergePosts([], []));
    }

    // --- shapeMetrics -------------------------------------------------------

    public function test_shape_metrics_full(): void
    {
        $insights = ['data' => [
            ['name' => 'post_impressions', 'values' => [['value' => 1000]]],
            ['name' => 'post_impressions_unique', 'values' => [['value' => 800]]],
            ['name' => 'post_clicks', 'values' => [['value' => 42]]],
        ]];
        $fields = [
            'reactions' => ['summary' => ['total_count' => 15]],
            'comments' => ['summary' => ['total_count' => 7]],
            'shares' => ['count' => 3],
        ];
        $this->assertSame([
            'impressions' => 1000,
            'reach' => 800,
            'clicks' => 42,
            'reactions' => 15,
            'comments' => 7,
            'shares' => 3,
        ], InsightsLogic::shapeMetrics($insights, $fields));
    }

    public function test_shape_metrics_tolerates_missing_keys(): void
    {
        $this->assertSame([
            'impressions' => 0,
            'reach' => 0,
            'clicks' => 0,
            'reactions' => 0,
            'comments' => 0,
            'shares' => 0,
        ], InsightsLogic::shapeMetrics([], []));
    }

    public function test_shape_metrics_partial(): void
    {
        $insights = ['data' => [
            ['name' => 'post_impressions', 'values' => [['value' => 5]]],
        ]];
        $fields = ['shares' => ['count' => 2]];
        $out = InsightsLogic::shapeMetrics($insights, $fields);
        $this->assertSame(5, $out['impressions']);
        $this->assertSame(0, $out['reach']);
        $this->assertSame(0, $out['reactions']);
        $this->assertSame(2, $out['shares']);
    }

    public function test_shape_metrics_casts_to_int(): void
    {
        $insights = ['data' => [
            ['name' => 'post_clicks', 'values' => [['value' => '99']]],
        ]];
        $out = InsightsLogic::shapeMetrics($insights, []);
        $this->assertSame(99, $out['clicks']);
    }
}
