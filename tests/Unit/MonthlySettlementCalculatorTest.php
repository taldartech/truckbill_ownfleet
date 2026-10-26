<?php

namespace Tests\Unit;

use App\MonthlyDriverWage;
use App\Support\MonthlySettlementCalculator;
use Tests\TestCase;

class MonthlySettlementCalculatorTest extends TestCase
{
    public function test_daily_rate_calculation(): void
    {
        $this->assertSame(1000.0, MonthlySettlementCalculator::dailyRate(30000, 30));
        $this->assertSame(0.0, MonthlySettlementCalculator::dailyRate(0, 30));
        $this->assertSame(0.0, MonthlySettlementCalculator::dailyRate(30000, 0));
    }

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
        $this->assertSame(
            0.0,
            MonthlySettlementCalculator::grossForPayableDays(0, 30, 30, 0, 31, 31),
        );
        $this->assertSame(
            0.0,
            MonthlySettlementCalculator::grossForPayableDays(30000, 0, 0, 31, 31, 31),
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

    public function test_fixed_thirty_day_partial_payable_fallback(): void
    {
        $this->assertSame(
            15000.0,
            MonthlySettlementCalculator::grossForPayableDays(30000, 15, 15, 0, 20, 30),
        );
    }

    public function test_calendar_divisor_payable_days_boundary(): void
    {
        $this->assertSame(
            30000.0,
            MonthlySettlementCalculator::grossForPayableDays(30000, 31, 30, 1, 30, 28),
        );
    }

    public function test_absent_deduction_uses_fixed_thirty_day_divisor(): void
    {
        $this->assertSame(
            1000.0,
            MonthlySettlementCalculator::absentDeductionForDays(1, 30000, 31, 30),
        );
        $this->assertSame(
            0.0,
            MonthlySettlementCalculator::absentDeductionForDays(0, 30000, 31, 30),
        );
        $this->assertSame(
            0.0,
            MonthlySettlementCalculator::absentDeductionForDays(5, 0, 31, 30),
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

    public function test_net_salary_calculation_with_and_without_rounding(): void
    {
        $wage = new MonthlyDriverWage([
            'gross_earning' => 25000.50,
            'hra' => 2000.00,
            'holiday_allowance' => 500.00,
            'bonus' => 1000.00,
            'pf' => 1800.00,
            'esi' => 250.00,
            'other_deduction' => 100.00,
            'advance_deducted' => 3000.00,
            'diesel_deduction' => 500.00,
            'round_net' => false,
        ]);

        // Net = (25000.50 + 200) + (2000 + 500 + 1000) - (1800 + 250 + 100 + 3000 + 500)
        // Net = 25200.50 + 3500 - 5650 = 23050.50
        $this->assertSame(23050.50, MonthlySettlementCalculator::netSalary($wage, 200.00));

        // Test with round_net = true
        $wage->round_net = true;
        $this->assertSame(23051.0, MonthlySettlementCalculator::netSalary($wage, 200.00));
    }

    public function test_total_deductions_and_extra_deductions(): void
    {
        $wage = new MonthlyDriverWage([
            'pf' => 1200.00,
            'esi' => 200.00,
            'other_deduction' => 100.00,
            'advance_deducted' => 1500.00,
            'diesel_deduction' => 300.00,
        ]);

        $this->assertSame(3300.00, MonthlySettlementCalculator::totalDeductions($wage));
        $this->assertSame(1500.00, MonthlySettlementCalculator::extraDeductions($wage));
    }

    public function test_payable_days_zero_with_present_days_greater_than_zero(): void
    {
        // Hits payableDays <= 0 with FIXED_DAYS
        $this->assertSame(
            0.0,
            MonthlySettlementCalculator::grossForPayableDays(30000, 0, 5, 0, 31, 30),
        );

        // Hits payableDays <= 0 with other divisor
        $this->assertSame(
            0.0,
            MonthlySettlementCalculator::grossForPayableDays(30000, 0, 5, 0, 31, 31),
        );
    }

    public function test_zero_divisor_edge_cases(): void
    {
        $this->assertSame(
            0.0,
            MonthlySettlementCalculator::grossForPayableDays(30000, 30, 30, 0, 31, 0),
        );

        $this->assertSame(
            0.0,
            MonthlySettlementCalculator::absentDeductionForDays(5, 30000, 31, 0),
        );
    }

    public function test_absent_deduction_capped_when_deduction_exceeds_base(): void
    {
        $this->assertSame(
            30000.0,
            MonthlySettlementCalculator::absentDeductionForDays(25, 30000, 30, 20),
        );
    }

    public function test_fixed_thirty_day_zero_absent_full_month(): void
    {
        $this->assertSame(
            30000.0,
            MonthlySettlementCalculator::grossForPayableDays(30000, 31, 31, 0, 31, 30),
        );
    }
}
