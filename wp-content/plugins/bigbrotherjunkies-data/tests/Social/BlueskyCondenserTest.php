<?php

namespace BigBrotherJunkies\Tests\Social;

use BigBrotherJunkies\Data\Social\BlueskyCondenser;
use PHPUnit\Framework\TestCase;

class BlueskyCondenserTest extends TestCase
{
    // --- condense() fast path (no settings/API access) ---

    public function testCondenseReturnsShortBodyUntouched(): void
    {
        $body = 'Feeds are back, everyone is asleep.';
        $this->assertSame($body, BlueskyCondenser::condense($body, 280));
    }

    public function testCondenseReturnsExactBudgetBodyUntouched(): void
    {
        $body = str_repeat('a', 280);
        $this->assertSame($body, BlueskyCondenser::condense($body, 280));
    }

    // --- fallbackTrim() ---

    public function testFallbackTrimLeavesFittingTextAlone(): void
    {
        $this->assertSame('Short one.', BlueskyCondenser::fallbackTrim('Short one.', 100));
    }

    public function testFallbackTrimCutsAtLastSentenceEnd(): void
    {
        $body = 'First thing happened. Second thing happened too. '
            . 'And then a third thing went on far longer than the budget allows here.';
        $out = BlueskyCondenser::fallbackTrim($body, 60);
        $this->assertSame('First thing happened. Second thing happened too.', $out);
        $this->assertLessThanOrEqual(60, mb_strlen($out));
    }

    public function testFallbackTrimHandlesQuestionAndExclamation(): void
    {
        $body = 'Is the veto happening today? Nobody on the feeds seems sure about the timing of anything at all right now.';
        $out = BlueskyCondenser::fallbackTrim($body, 40);
        $this->assertSame('Is the veto happening today?', $out);
    }

    public function testFallbackTrimNeverCutsMidWord(): void
    {
        $body = 'wordone wordtwo wordthree wordfour wordfive wordsix wordseven wordeight';
        $out = BlueskyCondenser::fallbackTrim($body, 30);
        $this->assertLessThanOrEqual(30, mb_strlen($out));
        $this->assertStringEndsWith('…', $out);
        // Every chunk before the ellipsis must be a complete input word.
        $words = explode(' ', rtrim($out, '…'));
        foreach ($words as $w) {
            $this->assertContains(trim($w), explode(' ', $body));
        }
    }

    public function testFallbackTrimIgnoresTooEarlySentenceEnd(): void
    {
        // Sentence end at char 4 is < 40% of the 60 budget — a word cut keeps more.
        $body = 'Hey. ' . str_repeat('word ', 30);
        $out = BlueskyCondenser::fallbackTrim($body, 60);
        $this->assertNotSame('Hey.', $out);
        $this->assertLessThanOrEqual(60, mb_strlen($out));
        $this->assertStringEndsWith('…', $out);
    }

    public function testFallbackTrimIsMultibyteSafe(): void
    {
        $body = str_repeat('héllo wörld ', 20);
        $out = BlueskyCondenser::fallbackTrim($body, 50);
        $this->assertLessThanOrEqual(50, mb_strlen($out));
        // A byte-level cut would corrupt the accented chars; valid UTF-8 proves it didn't.
        $this->assertTrue(mb_check_encoding($out, 'UTF-8'));
        $this->assertStringNotContainsString("\u{FFFD}", $out);
    }
}
