<?php

use PHPUnit\Framework\TestCase;
use BigBrotherJunkies\Data\Billing\FoundingBadge;

/**
 * The plan half of the badge auto-grant rule. Pure - no WP, no window.
 * Both entitlement paths (createSubscription, changePlan) route through
 * FoundingBadge::maybeGrantForPlan(), which asks this first.
 */
class FoundingBadgeQualifiesTest extends TestCase
{
    public function testAllAccessAnnualQualifies(): void
    {
        $this->assertTrue(FoundingBadge::qualifiesForPlan('annual'));
    }

    public function testLifetimeQualifies(): void
    {
        $this->assertTrue(FoundingBadge::qualifiesForPlan('lifetime'));
    }

    public function testMonthlyDoesNotQualify(): void
    {
        // Covers full_bean_monthly too: resolvePlan() maps it to plan_type 'monthly'.
        $this->assertFalse(FoundingBadge::qualifiesForPlan('monthly'));
    }

    public function testFullBeanAnnualQualifies(): void
    {
        // Decision 2026-09-12: the rule is tier-blind. resolvePlan() maps
        // 'full_bean_annual' to plan_type 'annual', so it lands here.
        $plan = \BigBrotherJunkies\Data\Billing\SubscriptionManager::resolvePlan('full_bean_annual');
        $this->assertSame('annual', $plan['plan_type']);
        $this->assertSame('full_bean', $plan['tier']);
        $this->assertTrue(FoundingBadge::qualifiesForPlan($plan['plan_type']));
    }

    public function testFullBeanMonthlyDoesNotQualify(): void
    {
        $plan = \BigBrotherJunkies\Data\Billing\SubscriptionManager::resolvePlan('full_bean_monthly');
        $this->assertSame('monthly', $plan['plan_type']);
        $this->assertFalse(FoundingBadge::qualifiesForPlan($plan['plan_type']));
    }

    public function testUnknownPlanDoesNotQualify(): void
    {
        $this->assertFalse(FoundingBadge::qualifiesForPlan(''));
    }
}
