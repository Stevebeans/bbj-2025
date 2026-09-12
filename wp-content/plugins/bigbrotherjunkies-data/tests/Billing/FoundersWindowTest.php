<?php

use PHPUnit\Framework\TestCase;
use BigBrotherJunkies\Data\Billing\FoundersWindow;

class FoundersWindowTest extends TestCase
{
    public function testClosedWhenDisabled(): void
    {
        $this->assertFalse(FoundersWindow::isOpenPure(['enabled' => false], time()));
    }

    public function testOpenWhenEnabledAndNoCloseDate(): void
    {
        $this->assertTrue(FoundersWindow::isOpenPure(['enabled' => true, 'closes_at' => ''], time()));
    }

    public function testClosesAtBoundary(): void
    {
        $s = ['enabled' => true, 'closes_at' => '2026-10-08 16:00:00'];
        $closeTs = strtotime('2026-10-08 16:00:00 UTC');
        $this->assertTrue(FoundersWindow::isOpenPure($s, $closeTs - 1));
        $this->assertFalse(FoundersWindow::isOpenPure($s, $closeTs));
        $this->assertFalse(FoundersWindow::isOpenPure($s, $closeTs + 86400));
    }

    public function testGarbageCloseDateFailsOpen(): void
    {
        $this->assertTrue(FoundersWindow::isOpenPure(['enabled' => true, 'closes_at' => 'not-a-date'], time()));
    }
}
