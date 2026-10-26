<?php

namespace Tests\Unit\Support;

use App\Support\SubscriptionProration;
use Carbon\Carbon;
use Tests\TestCase;

class SubscriptionProrationTest extends TestCase
{
    public function test_remaining_days_includes_today_and_end_date(): void
    {
        Carbon::setTestNow('2026-01-01');

        $this->assertSame(170, SubscriptionProration::remainingDaysInclusive('2026-06-19'));
        $this->assertSame(1, SubscriptionProration::remainingDaysInclusive('2026-01-01'));

        Carbon::setTestNow();
    }

    public function test_prorates_annual_amount_by_remaining_days(): void
    {
        $annual = 36500.0;
        $expected = round(($annual / 365) * 170, 2);

        $this->assertSame($expected, SubscriptionProration::prorateAnnualAmount($annual, 170));
    }

    public function test_prorated_amount_never_exceeds_annual_price(): void
    {
        $annual = 1000.0;

        $this->assertSame($annual, SubscriptionProration::prorateAnnualAmount($annual, 365));
        $this->assertSame($annual, SubscriptionProration::prorateAnnualAmount($annual, 400));
    }

    public function test_apply_upcoming_subscriptions_command(): void
    {
        $mock = $this->createMock(\App\Services\SubscriptionCheckoutService::class);
        $mock->expects($this->once())
            ->method('applyDueUpcomingSubscriptions')
            ->willReturn(2);
        $this->app->instance(\App\Services\SubscriptionCheckoutService::class, $mock);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('subscription:apply-upcoming');
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Applied 2 upcoming subscription(s).', \Illuminate\Support\Facades\Artisan::output());
    }

    public function test_remaining_days_returns_zero_when_end_date_has_passed(): void
    {
        $asOf = Carbon::parse('2026-07-10');
        $this->assertSame(0, SubscriptionProration::remainingDaysInclusive('2026-07-09', $asOf));
    }

    public function test_prorate_annual_amount_returns_zero_for_zero_or_negative_inputs(): void
    {
        $this->assertSame(0.0, SubscriptionProration::prorateAnnualAmount(0.0, 100));
        $this->assertSame(0.0, SubscriptionProration::prorateAnnualAmount(-500.0, 100));
        $this->assertSame(0.0, SubscriptionProration::prorateAnnualAmount(1000.0, 0));
        $this->assertSame(0.0, SubscriptionProration::prorateAnnualAmount(1000.0, -10));
    }
}
