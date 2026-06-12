<?php

namespace App\Support;

use App\MonthlyDriverWage;

class MonthlySettlementCalculator
{
    public static function dailyRate(float $monthlyBase, int $payDaysDivisor): float
    {
        if ($monthlyBase <= 0 || $payDaysDivisor <= 0) {
            return 0.0;
        }

        return round($monthlyBase / $payDaysDivisor, 2);
    }

    public static function grossForPayableDays(
        float $monthlyBase,
        int $payableDays,
        int $presentDays,
        int $absentDays,
        int $calendarDays,
        int $payDaysDivisor,
    ): float {
        if ($monthlyBase <= 0 || $payDaysDivisor <= 0) {
            return 0.0;
        }

        if ($payableDays <= 0 && $presentDays <= 0) {
            return 0.0;
        }

        if ($payDaysDivisor === PayrollPayDays::FIXED_DAYS) {
            if ($absentDays === 0 && $payableDays >= $calendarDays) {
                return round($monthlyBase, 2);
            }

            if ($absentDays > 0 && ($presentDays + $absentDays) >= $calendarDays) {
                return max(
                    0.0,
                    round($monthlyBase - ($monthlyBase * $absentDays / $payDaysDivisor), 2),
                );
            }

            if ($payableDays <= 0) {
                return 0.0;
            }

            return round($monthlyBase * $payableDays / $payDaysDivisor, 2);
        }

        if ($payableDays <= 0) {
            return 0.0;
        }

        if ($payableDays >= $calendarDays) {
            return round($monthlyBase, 2);
        }

        return round($monthlyBase * $payableDays / $payDaysDivisor, 2);
    }

    public static function absentDeductionForDays(
        int $absentDays,
        float $monthlyBase,
        int $calendarDays,
        int $payDaysDivisor,
    ): float {
        if ($absentDays <= 0 || $monthlyBase <= 0 || $payDaysDivisor <= 0) {
            return 0.0;
        }

        if ($absentDays >= $calendarDays) {
            return round($monthlyBase, 2);
        }

        $deduction = round($monthlyBase * $absentDays / $payDaysDivisor, 2);

        return min(round($monthlyBase, 2), $deduction);
    }

    /**
     * Net payable = gross earnings minus PF/ESI/other/advance/diesel.
     * Absent deduction is shown for reference (absent days × daily rate), not subtracted again from gross.
     */
    public static function netSalary(MonthlyDriverWage $wage, float $substitutePay = 0.0): float
    {
        $gross = (float) $wage->gross_earning + max(0, $substitutePay);
        $additions = (float) $wage->hra
            + (float) $wage->holiday_allowance
            + (float) $wage->bonus;
        $deductions = (float) $wage->pf
            + (float) $wage->esi
            + (float) $wage->other_deduction
            + (float) $wage->advance_deducted
            + (float) $wage->diesel_deduction;

        $net = max(0, $gross + $additions - $deductions);

        if ($wage->round_net) {
            return (float) round($net, 0);
        }

        return round($net, 2);
    }

    public static function totalDeductions(MonthlyDriverWage $wage): float
    {
        return round(
            (float) $wage->pf
            + (float) $wage->esi
            + (float) $wage->other_deduction
            + (float) $wage->advance_deducted
            + (float) $wage->diesel_deduction,
            2,
        );
    }

    public static function extraDeductions(MonthlyDriverWage $wage): float
    {
        return round((float) $wage->pf + (float) $wage->esi + (float) $wage->other_deduction, 2);
    }
}
