<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class MonthlyWageAccrual
{
    public static function perDay(float $monthlyBase, string $date): float
    {
        if ($monthlyBase <= 0) {
            return 0.0;
        }

        $daysInMonth = Carbon::parse($date)->daysInMonth;

        return round($monthlyBase / $daysInMonth, 2);
    }
}
