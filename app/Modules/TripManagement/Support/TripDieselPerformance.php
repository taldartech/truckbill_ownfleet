<?php

namespace App\Modules\TripManagement\Support;

use App\Trip;

class TripDieselPerformance
{
    /**
     * @return array<string, mixed>|null
     */
    public static function forTrip(Trip $trip): ?array
    {
        return TripFuelCalculator::calculatePerformanceSummary($trip);
    }
}
