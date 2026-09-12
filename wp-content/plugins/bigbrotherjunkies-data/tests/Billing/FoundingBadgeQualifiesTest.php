<?php

use PHPUnit\Framework\TestCase;
use BigBrotherJunkies\Data\Billing\FoundingBadge;

/**
 * The plan/tier half of the badge auto-grant rule. Pure - no WP, no window.
 * Both entitlement paths (createSubscription, changePlan) route through
 * FoundingBadge::maybeGrantForPlan(), which asks this first.
 */
class FoundingBadgeQualifiesTest extends TestCase
{
    public function testAllAccessAnnualQualifies(): void
    {
        $this->assertTrue(FoundingBadge::qualifiesForPlan('annual', 'supporter'));
    }

    public function testLifetimeQualifies(): void
    {
        $this->assertTrue(FoundingBadge::qualifiesForPlan('lifetime', 'supporter'));
    }

    public function testMonthlyDoesNotQualify(): void
    {
        $this->assertFalse(FoundingBadge::qualifiesForPlan('monthly', 'supporter'));
    }

    public function testFullBeanTierDoesNotQualify(): void
    {
        // Plan defect I5/I7 in the review: deliberate for now, Steve's call.
        $this->assertFalse(FoundingBadge::qualifiesForPlan('annual', 'full_bean'));
    }

    public function testTierDefaultsToSupporter(): void
    {
        $this->assertTrue(FoundingBadge::qualifiesForPlan('annual'));
    }
}
