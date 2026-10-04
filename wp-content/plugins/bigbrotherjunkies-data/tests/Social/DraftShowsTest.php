<?php

namespace BigBrotherJunkies\Data\Tests\Social;

use BigBrotherJunkies\Data\Social\DraftService;
use BigBrotherJunkies\Data\Social\DraftShows;
use PHPUnit\Framework\TestCase;

class DraftShowsTest extends TestCase
{
    public function test_only_bb_and_survivor_are_known(): void
    {
        $this->assertTrue(DraftShows::isKnown(''));
        $this->assertTrue(DraftShows::isKnown('survivor'));
        foreach (['Survivor', 'big-brother', 'all', 'any', 'amazing-race'] as $show) {
            $this->assertFalse(DraftShows::isKnown($show), $show);
        }
    }

    public function test_bb_keeps_the_original_blog_kind_prompt_and_label(): void
    {
        $this->assertSame('blog', DraftShows::blogKind(''));
        $this->assertSame(DraftService::BLOG_PROMPT, DraftShows::blogPrompt(''));
        $this->assertSame('Big Brother 28', DraftShows::label(''));
    }

    public function test_survivor_gets_its_own_kind_prompt_and_label(): void
    {
        // A distinct kind keeps Survivor recaps out of WeekSummaryService's
        // BB-only ['digest', 'blog'] read.
        $this->assertSame('blog_surv', DraftShows::blogKind('survivor'));
        $this->assertSame(DraftService::SURVIVOR_BLOG_PROMPT, DraftShows::blogPrompt('survivor'));
        $this->assertSame('Survivor 49', DraftShows::label('survivor'));
    }

    public function test_blog_kinds_fit_the_summary_kind_column(): void
    {
        $this->assertSame(['blog', 'blog_surv'], DraftShows::blogKinds());
        foreach (DraftShows::blogKinds() as $kind) {
            $this->assertLessThanOrEqual(12, strlen($kind), $kind); // kind VARCHAR(12)
        }
    }

    public function test_survivor_prompt_uses_survivor_vocabulary_not_big_brother(): void
    {
        $p = DraftService::SURVIVOR_BLOG_PROMPT;
        $this->assertStringContainsString('Survivor 49 Spoilers:', $p);
        $this->assertStringContainsString('Tribal Council', $p);
        $this->assertStringContainsString('Never invent or guess who was voted out', $p);
        $this->assertStringNotContainsString('—', $p);
        // BB terms only appear inside the "never mention" list.
        $this->assertSame(1, substr_count($p, 'houseguests'));
        $this->assertStringNotContainsString('HOUSE UPDATES', $p);
        $this->assertStringNotContainsString('game-state', $p);
    }
}
