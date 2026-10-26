<?php

namespace Tests\Unit;

use App\Support\MonthlyWageAccrual;
use PHPUnit\Framework\TestCase;

class MonthlyWageAccrualTest extends TestCase
{
    public function test_per_day_divides_monthly_base_by_days_in_month(): void
    {
        $this->assertSame(33.33, MonthlyWageAccrual::perDay(1000, '2026-06-15'));
        $this->assertSame(31.67, MonthlyWageAccrual::perDay(950, '2026-06-01'));
    }

    public function test_per_day_accepts_explicit_pay_days_divisor(): void
    {
        $this->assertSame(33.33, MonthlyWageAccrual::perDay(1000, '2026-01-31', 30));
    }

    public function test_per_day_returns_zero_when_monthly_base_is_zero(): void
    {
        $this->assertSame(0.0, MonthlyWageAccrual::perDay(0, '2026-06-15'));
    }

    public function test_per_day_returns_zero_when_divisor_is_zero(): void
    {
        $this->assertSame(0.0, MonthlyWageAccrual::perDay(1000, '2026-06-15', 0));
    }
}
