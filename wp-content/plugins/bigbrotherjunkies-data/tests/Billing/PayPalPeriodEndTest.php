<?php

use PHPUnit\Framework\TestCase;
use BigBrotherJunkies\Data\Billing\SubscriptionManager;

/**
 * SubscriptionManager::paypalPeriodEnd() derives the paid-through date from
 * a PayPal subscription payload. PayPal renewals never used to advance
 * current_period_end (2026-09-17: a member who canceled in month 3 lost
 * access instantly because the row still held her month-1 date).
 */
class PayPalPeriodEndTest extends TestCase
{
    public function testPrefersNextBillingTime(): void
    {
        $sub = ['billing_info' => [
            'next_billing_time' => '2026-10-07T10:00:00Z',
            'last_payment'      => ['time' => '2026-09-07T10:00:00Z'],
        ]];
        $this->assertSame('2026-10-07 10:00:00', SubscriptionManager::paypalPeriodEnd($sub, 'monthly'));
    }

    public function testFallsBackToLastPaymentPlusOneMonthWhenNextBillingMissing(): void
    {
        // PayPal drops next_billing_time once a subscription is cancelled;
        // the member is still paid through last payment + one cycle.
        $sub = ['billing_info' => [
            'last_payment' => ['time' => '2026-09-07T10:00:00Z'],
        ]];
        $this->assertSame('2026-10-07 10:00:00', SubscriptionManager::paypalPeriodEnd($sub, 'monthly'));
    }

    public function testFallsBackToLastPaymentPlusOneYearForAnnual(): void
    {
        $sub = ['billing_info' => [
            'last_payment' => ['time' => '2026-09-07T10:00:00Z'],
        ]];
        $this->assertSame('2027-09-07 10:00:00', SubscriptionManager::paypalPeriodEnd($sub, 'annual'));
    }

    public function testNormalisesNonUtcTimestampsToUtc(): void
    {
        $sub = ['billing_info' => ['next_billing_time' => '2026-10-07T06:00:00-04:00']];
        $this->assertSame('2026-10-07 10:00:00', SubscriptionManager::paypalPeriodEnd($sub, 'monthly'));
    }

    public function testReturnsNullWhenPayloadHasNoBillingDates(): void
    {
        $this->assertNull(SubscriptionManager::paypalPeriodEnd([], 'monthly'));
        $this->assertNull(SubscriptionManager::paypalPeriodEnd(['billing_info' => []], 'monthly'));
        $this->assertNull(SubscriptionManager::paypalPeriodEnd(['error' => 'boom'], 'monthly'));
    }

    public function testReturnsNullForUnparseableDates(): void
    {
        $sub = ['billing_info' => ['next_billing_time' => 'not a date']];
        $this->assertNull(SubscriptionManager::paypalPeriodEnd($sub, 'monthly'));
    }

    public function testLifetimeHasNoPeriodEnd(): void
    {
        $sub = ['billing_info' => ['last_payment' => ['time' => '2026-09-07T10:00:00Z']]];
        $this->assertNull(SubscriptionManager::paypalPeriodEnd($sub, 'lifetime'));
    }
}
