<?php

namespace Tests\Unit;

use App\Support\MonthlySettlementCalculator;
use PHPUnit\Framework\TestCase;

class MonthlySettlementCalculatorTest extends TestCase
{
    public function test_full_month_payable_days_equal_monthly_base(): void
    {
        $this->assertSame(
            30000.0,
            MonthlySettlementCalculator::grossForPayableDays(30000, 31, 31, 0, 31, 31),
        );
    }

    public function test_partial_month_uses_proportional_gross_with_calendar_divisor(): void
    {
        $this->assertSame(
            29032.26,
            MonthlySettlementCalculator::grossForPayableDays(30000, 30, 30, 0, 31, 31),
        );
    }

    public function test_partial_month_uses_fixed_thirty_day_divisor(): void
    {
        $this->assertSame(
            9000.0,
            MonthlySettlementCalculator::grossForPayableDays(30000, 9, 9, 1, 31, 30),
        );
    }

    public function test_fixed_thirty_day_full_calendar_month_with_one_absent(): void
    {
        $this->assertSame(
            29000.0,
            MonthlySettlementCalculator::grossForPayableDays(30000, 30, 30, 1, 31, 30),
        );
    }

    public function test_fixed_thirty_day_full_absent_month_returns_zero_gross(): void
    {
        $this->assertSame(
            0.0,
            MonthlySettlementCalculator::grossForPayableDays(30000, 0, 0, 28, 28, 30),
        );
    }

    public function test_absent_deduction_uses_fixed_thirty_day_divisor(): void
    {
        $this->assertSame(
            1000.0,
            MonthlySettlementCalculator::absentDeductionForDays(1, 30000, 31, 30),
        );
    }

    public function test_full_month_absent_deduction_caps_at_monthly_base(): void
    {
        $this->assertSame(
            30000.0,
            MonthlySettlementCalculator::absentDeductionForDays(31, 30000, 31, 30),
        );
    }

    public function test_february_full_absent_deduction_caps_at_monthly_base(): void
    {
        $this->assertSame(
            30000.0,
            MonthlySettlementCalculator::absentDeductionForDays(28, 30000, 28, 30),
        );
    }
}
