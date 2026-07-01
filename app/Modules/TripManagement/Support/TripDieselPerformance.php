<?php

namespace App\Modules\TripManagement\Support;

use App\Trip;
use App\TripRouteLeg;
use Illuminate\Support\Collection;

class TripDieselPerformance
{
    /**
     * @return array<string, mixed>|null
     */
    public static function forTrip(Trip $trip): ?array
    {
        if (! $trip->relationLoaded('routeLegs')) {
            $trip->load('routeLegs');
        }

        return self::calculate(
            $trip->routeLegs,
            $trip->expected_diesel_liters,
            $trip->diesel_rate_per_liter,
            $trip->total_distance_km,
        );
    }

    /**
     * @param  Collection<int, TripRouteLeg>|list<TripRouteLeg>  $routeLegs
     * @return array<string, mixed>|null
     */
    public static function calculate(
        Collection|array $routeLegs,
        mixed $expectedDieselLiters,
        mixed $dieselRatePerLiter,
        mixed $actualTotalKm,
    ): ?array {
        $expectedAvgMileage = self::resolveExpectedAvgMileage($routeLegs);
        $expectedDiesel = (float) ($expectedDieselLiters ?? 0);
        $dieselRate = (float) ($dieselRatePerLiter ?? 0);
        $actualKm = (float) ($actualTotalKm ?? 0);

        if ($expectedAvgMileage <= 0 || $expectedDiesel <= 0) {
            return null;
        }

        $expectedTotalKm = round($expectedDiesel * $expectedAvgMileage, 2);
        $totalTripCost = $dieselRate > 0 ? round($expectedDiesel * $dieselRate, 2) : null;
        $ratePerKm = $totalTripCost !== null && $expectedTotalKm > 0
            ? round($totalTripCost / $expectedTotalKm, 2)
            : null;
        $hasActual = $actualKm > 0;

        $extraKm = 0.0;
        $unusedKm = 0.0;
        $performanceStatus = $hasActual ? 'within' : 'pending';

        if ($hasActual) {
            $diffKm = round($actualKm - $expectedTotalKm, 2);

            if ($diffKm > 0) {
                $extraKm = $diffKm;
                $performanceStatus = 'over';
            } elseif ($diffKm < 0) {
                $unusedKm = abs($diffKm);
                $performanceStatus = 'under';
            }
        }

        $extraDiesel = $extraKm > 0
            ? round($extraKm / $expectedAvgMileage, 2)
            : 0.0;
        $extraDieselCost = $extraDiesel > 0 && $dieselRate > 0
            ? round($extraDiesel * $dieselRate, 2)
            : 0.0;
        $remainingAmount = $unusedKm > 0 && $ratePerKm !== null
            ? round($unusedKm * $ratePerKm, 2)
            : 0.0;

        return [
            'expected_avg_mileage_km_per_liter' => round($expectedAvgMileage, 2),
            'expected_diesel_liters' => round($expectedDiesel, 2),
            'diesel_rate_per_liter' => $dieselRate > 0 ? round($dieselRate, 2) : null,
            'total_trip_cost' => $totalTripCost,
            'expected_total_km' => $expectedTotalKm,
            'actual_total_km' => $hasActual ? round($actualKm, 2) : null,
            'rate_per_km' => $ratePerKm,
            'unused_km' => $hasActual ? round($unusedKm, 2) : null,
            'extra_km' => $hasActual ? $extraKm : null,
            'remaining_amount' => $hasActual ? $remainingAmount : null,
            'performance_status' => $performanceStatus,
            'extra_diesel_liters' => $hasActual ? $extraDiesel : null,
            'extra_diesel_cost' => $hasActual ? $extraDieselCost : null,
        ];
    }

    /**
     * @param  Collection<int, TripRouteLeg>|list<TripRouteLeg>  $routeLegs
     */
    private static function resolveExpectedAvgMileage(Collection|array $routeLegs): float
    {
        $legs = $routeLegs instanceof Collection ? $routeLegs : collect($routeLegs);

        $mileages = $legs
            ->map(fn (TripRouteLeg|array $leg): float => is_array($leg)
                ? (float) ($leg['expected_mileage_km_per_liter'] ?? 0)
                : (float) ($leg->expected_mileage_km_per_liter ?? 0))
            ->filter(fn (float $mileage): bool => $mileage > 0);

        if ($mileages->isEmpty()) {
            return 0.0;
        }

        return round((float) $mileages->avg(), 2);
    }
}
