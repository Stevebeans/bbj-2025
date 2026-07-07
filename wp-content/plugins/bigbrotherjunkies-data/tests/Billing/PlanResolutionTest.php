<?php

use PHPUnit\Framework\TestCase;
use BigBrotherJunkies\Data\Billing\SubscriptionManager;

class PlanResolutionTest extends TestCase
{
    public function testResolvesFullBeanMonthlyToEnumSafeValues(): void
    {
        $plan = SubscriptionManager::resolvePlan('full_bean_monthly');
        $this->assertSame('monthly', $plan['plan_type']); // ENUM-safe
        $this->assertSame('full_bean', $plan['tier']);
        $this->assertSame('full_bean', $plan['role']);
        $this->assertSame(1499, $plan['amount_cents']);
    }

    public function testResolvesSupporterAnnual(): void
    {
        $plan = SubscriptionManager::resolvePlan('annual');
        $this->assertSame('annual', $plan['plan_type']);
        $this->assertSame('supporter', $plan['tier']);
        $this->assertSame(3500, $plan['amount_cents']);
    }

    public function testResolveUnknownKeyReturnsNull(): void
    {
        $this->assertNull(SubscriptionManager::resolvePlan('full_bean_lifetime'));
    }

    public function testIsSamePlanComparesTypeAndTier(): void
    {
        $supporterMonthlyRow = ['plan_type' => 'monthly', 'tier' => 'supporter'];
        // Same plan_type, different tier → NOT the same plan (the old string
        // comparison would have mis-handled this).
        $this->assertFalse(SubscriptionManager::isSamePlan($supporterMonthlyRow, 'full_bean_monthly'));
        $this->assertTrue(SubscriptionManager::isSamePlan($supporterMonthlyRow, 'monthly'));

        $beanMonthlyRow = ['plan_type' => 'monthly', 'tier' => 'full_bean'];
        $this->assertTrue(SubscriptionManager::isSamePlan($beanMonthlyRow, 'full_bean_monthly'));
        $this->assertFalse(SubscriptionManager::isSamePlan($beanMonthlyRow, 'monthly'));
    }

    public function testIsSamePlanTreatsMissingTierAsSupporter(): void
    {
        // Pre-migration rows may lack the tier key entirely.
        $legacyRow = ['plan_type' => 'annual'];
        $this->assertTrue(SubscriptionManager::isSamePlan($legacyRow, 'annual'));
        $this->assertFalse(SubscriptionManager::isSamePlan($legacyRow, 'full_bean_annual'));
    }

    public function testDisplayNamesAreTierAware(): void
    {
        $this->assertSame('Monthly Supporter', SubscriptionManager::displayName('monthly'));
        $this->assertSame('Season Pass', SubscriptionManager::displayName('annual', 'supporter'));
        $this->assertSame('Lifetime Member', SubscriptionManager::displayName('lifetime'));
        $this->assertSame('Full Bean Monthly', SubscriptionManager::displayName('monthly', 'full_bean'));
        $this->assertSame('Full Bean Annual', SubscriptionManager::displayName('annual', 'full_bean'));
    }

    public function testLifetimeRoleIsNeverReplaceable(): void
    {
        $this->assertNotContains('lifetime', SubscriptionManager::REPLACEABLE_ROLES);
        $this->assertContains('supporter', SubscriptionManager::REPLACEABLE_ROLES);
        $this->assertContains('full_bean', SubscriptionManager::REPLACEABLE_ROLES);
        $this->assertContains('subscriber', SubscriptionManager::REPLACEABLE_ROLES);
    }
}
