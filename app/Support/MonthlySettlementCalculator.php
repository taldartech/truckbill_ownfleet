<?php

namespace App\Support;

use App\MonthlyDriverWage;

class MonthlySettlementCalculator
{
    public static function dailyRate(float $monthlyBase, int $calendarDays): float
    {
        if ($monthlyBase <= 0 || $calendarDays <= 0) {
            return 0.0;
        }

        return round($monthlyBase / $calendarDays, 2);
    }

    public static function absentDeductionForDays(int $absentDays, float $monthlyBase, int $calendarDays): float
    {
        return round($absentDays * self::dailyRate($monthlyBase, $calendarDays), 2);
    }

    /**
     * Net payable = gross earnings minus PF/ESI/other/advance.
     * Absent deduction is shown for reference (absent days × daily rate), not subtracted again from gross.
     */
    public static function netSalary(MonthlyDriverWage $wage): float
    {
        $gross = (float) $wage->gross_earning;
        $additions = (float) $wage->hra + (float) $wage->holiday_allowance;
        $deductions = (float) $wage->pf
            + (float) $wage->esi
            + (float) $wage->other_deduction
            + (float) $wage->advance_deducted;

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
            + (float) $wage->advance_deducted,
            2,
        );
    }

    public static function extraDeductions(MonthlyDriverWage $wage): float
    {
        return round((float) $wage->pf + (float) $wage->esi + (float) $wage->other_deduction, 2);
    }
}
