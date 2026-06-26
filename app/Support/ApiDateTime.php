<?php

namespace App\Support;

use Carbon\CarbonInterface;

class ApiDateTime
{
    public static function toIso(?CarbonInterface $dateTime): ?string
    {
        if ($dateTime === null) {
            return null;
        }

        return $dateTime
            ->copy()
            ->timezone(config('app.timezone', 'Asia/Kolkata'))
            ->toIso8601String();
    }
}
