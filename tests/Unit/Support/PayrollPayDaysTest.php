<?php

namespace Tests\Unit\Support;

use App\Enums\PayrollPayDaysMode;
use App\Support\CurrentTenant;
use App\Support\PayrollPayDays;
use App\Support\TenantContext;
use App\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollPayDaysTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::setTenantId(null);
        PayrollPayDays::clearCache();
        parent::tearDown();
    }

    public function test_mode_and_divisors(): void
    {
        PayrollPayDays::clearCache();

        $this->assertSame(PayrollPayDaysMode::Calendar, PayrollPayDays::modeForTenant(null));

        // Fixed 30 mode always divides by 30
        $this->assertSame(30, PayrollPayDays::divisor(31, PayrollPayDaysMode::Fixed30));
        $this->assertSame(30, PayrollPayDays::divisor(28, PayrollPayDaysMode::Fixed30));

        // Calendar mode divides by days in month (clamped to at least 1)
        $this->assertSame(31, PayrollPayDays::divisor(31, PayrollPayDaysMode::Calendar));
        $this->assertSame(1, PayrollPayDays::divisor(0, PayrollPayDaysMode::Calendar));

        // Divisor for date
        $this->assertSame(28, PayrollPayDays::divisorForDate('2026-02-15', PayrollPayDaysMode::Calendar));
        $this->assertSame(30, PayrollPayDays::divisorForDate('2026-02-15', PayrollPayDaysMode::Fixed30));
        $this->assertSame(30, PayrollPayDays::displayPayDays(31, PayrollPayDaysMode::Fixed30));
    }

    public function test_mode_resolves_from_tenant_database_and_cache(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Payroll Co',
            'slug' => 'payroll-co',
            'timezone' => 'Asia/Kolkata',
            'payroll_pay_days_mode' => PayrollPayDaysMode::Fixed30,
            'is_active' => true,
        ]);

        PayrollPayDays::clearCache();
        $this->assertSame(PayrollPayDaysMode::Fixed30, PayrollPayDays::modeForTenant($tenant->id));

        // From cache
        $this->assertSame(PayrollPayDaysMode::Fixed30, PayrollPayDays::modeForTenant($tenant->id));

        // Current tenant
        TenantContext::setTenantId($tenant->id);
        $this->assertSame(PayrollPayDaysMode::Fixed30, PayrollPayDays::modeForCurrentTenant());

        // Nonexistent tenant ID returns null from DB, falling back to calendar
        $this->assertSame(PayrollPayDaysMode::Calendar, PayrollPayDays::modeForTenant('nonexistent-uuid'));
    }
}
