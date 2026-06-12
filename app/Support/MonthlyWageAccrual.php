<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class MonthlyWageAccrual
{
    public static function perDay(float $monthlyBase, string $date, ?int $payDaysDivisor = null): float
    {
        if ($monthlyBase <= 0) {
            return 0.0;
        }

        $divisor = $payDaysDivisor ?? (int) Carbon::parse($date)->daysInMonth;

        if ($divisor <= 0) {
            return 0.0;
        }

        return round($monthlyBase / $divisor, 2);
    }
}
