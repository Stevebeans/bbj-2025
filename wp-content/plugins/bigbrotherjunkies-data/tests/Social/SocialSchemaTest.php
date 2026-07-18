<?php

namespace BigBrotherJunkies\Data\Tests\Social;

use BigBrotherJunkies\Data\Social\SocialSchema;
use BigBrotherJunkies\Data\Social\SocialStore;
use PHPUnit\Framework\TestCase;

class SocialSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        global $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public function get_charset_collate(): string
            {
                return 'DEFAULT CHARACTER SET utf8mb4';
            }
        };
    }

    public function test_quotes_schema_present_and_version_bumped(): void
    {
        $schemas = SocialSchema::getAllSchemas();
        $this->assertArrayHasKey(SocialSchema::TABLE_QUOTES, $schemas);
        $sql = $schemas[SocialSchema::TABLE_QUOTES];
        $this->assertStringContainsString('CREATE TABLE wp_bbj_player_quotes', $sql);
        foreach (['season_id', 'player_id', 'quote_text', 'context', 'said_on', 'source_count', 'created_at'] as $col) {
            $this->assertStringContainsString($col, $sql);
        }
        $this->assertStringContainsString('KEY idx_player (player_id)', $sql);
        $this->assertStringContainsString('KEY idx_season_day (season_id, said_on)', $sql);
        $this->assertSame('1.3.0', SocialStore::DB_VERSION);
    }

    public function test_posts_schema_has_engagement_columns_and_index(): void
    {
        $sql = SocialSchema::getAllSchemas()[SocialSchema::TABLE_POSTS];
        foreach (['likes', 'reposts', 'replies', 'quotes'] as $col) {
            $this->assertStringContainsString("{$col} INT(10) UNSIGNED NOT NULL DEFAULT 0", $sql);
        }
        $this->assertStringContainsString('KEY idx_posted (posted_at)', $sql);
    }
}
