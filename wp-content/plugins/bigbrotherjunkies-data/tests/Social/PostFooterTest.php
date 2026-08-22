<?php

namespace BigBrotherJunkies\Tests\Social;

use BigBrotherJunkies\Data\Social\PostFooter;
use PHPUnit\Framework\TestCase;

class PostFooterTest extends TestCase
{
    public function test_appends_after_blank_line(): void
    {
        $this->assertSame(
            "Taylor won the veto.\n\nSee the site.",
            PostFooter::applyTo("Taylor won the veto.\n", "See the site.")
        );
    }

    public function test_blank_footer_is_noop(): void
    {
        $this->assertSame('Body.', PostFooter::applyTo('Body.', ''));
        $this->assertSame('Body.', PostFooter::applyTo('Body.', '   '));
    }

    public function test_blank_body_is_noop(): void
    {
        $this->assertSame('', PostFooter::applyTo('', 'Footer.'));
        $this->assertSame('  ', PostFooter::applyTo('  ', 'Footer.'));
    }

    public function test_never_double_appends(): void
    {
        $once = PostFooter::applyTo('Body.', 'Footer.');
        $this->assertSame($once, PostFooter::applyTo($once, 'Footer.'));
    }

    public function test_default_footer_wording(): void
    {
        $this->assertSame(
            'Note: these posts can run behind. For up-to-the-minute updates, hit the Big Brother Junkies website.',
            PostFooter::DEFAULT_FOOTER
        );
        $this->assertStringNotContainsString("\u{2014}", PostFooter::DEFAULT_FOOTER);
    }
}
