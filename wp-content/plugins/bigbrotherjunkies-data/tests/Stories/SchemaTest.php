<?php
namespace BigBrotherJunkies\Data\Tests\Stories;

use BigBrotherJunkies\Data\Stories\Schema;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase
{
    protected function setUp(): void
    {
        global $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public function get_charset_collate(): string { return 'DEFAULT CHARACTER SET utf8mb4'; }
        };
    }

    public function test_items_schema_columns_and_indexes(): void
    {
        $sql = Schema::getAllSchemas()[Schema::TABLE_ITEMS];
        $this->assertStringContainsString('CREATE TABLE wp_bbj_story_items', $sql);
        foreach (['show_slug', 'source', 'source_key', 'url', 'title', 'excerpt', 'author', 'media_type',
                  'media_url', 'thumbnail_url', 'published_at', 'fetched_at', 'trust', 'score',
                  'processed', 'cluster_id', 'inbox_status', 'used_queue_id'] as $col) {
            $this->assertStringContainsString($col, $sql, "missing column {$col}");
        }
        $this->assertStringNotContainsString(' show ', $sql, 'show is a reserved word; use show_slug');
        $this->assertStringContainsString('UNIQUE KEY uniq_url (url)', $sql);
        $this->assertStringContainsString('KEY idx_show_inbox (show_slug, inbox_status, score)', $sql);
        $this->assertStringContainsString('KEY idx_show_processed (show_slug, processed, published_at)', $sql);
    }

    public function test_queue_and_runs_schemas_present(): void
    {
        $all = Schema::getAllSchemas();
        $this->assertStringContainsString('CREATE TABLE wp_bbj_story_queue', $all[Schema::TABLE_QUEUE]);
        foreach (['show_slug', 'cluster_id', 'label', 'blurb', 'evidence', 'sensitive', 'sensitive_reason',
                  'matches_post_id', 'status', 'created_at', 'expires_at', 'resolved_at', 'resolved_by',
                  'published_post_id'] as $col) {
            $this->assertStringContainsString($col, $all[Schema::TABLE_QUEUE]);
        }
        $this->assertStringContainsString('CREATE TABLE wp_bbj_story_runs', $all[Schema::TABLE_RUNS]);
        foreach (['show_slug', 'kind', 'started_at', 'finished_at', 'ingested', 'clusters', 'published',
                  'queued', 'dropped', 'errors'] as $col) {
            $this->assertStringContainsString($col, $all[Schema::TABLE_RUNS]);
        }
        $this->assertSame('1.0.0', Schema::DB_VERSION);
    }
}
