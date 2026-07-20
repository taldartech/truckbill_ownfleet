<?php

namespace App\Modules\TripManagement\Support;

use App\Trip;

class TripFuelCalculator
{
    public const CALCULATION_VERSION = 1;

    public static function calculateExpectedRunningKm(?float $startMeter, ?float $endMeter): ?float
    {
        if ($startMeter === null || $endMeter === null) {
            return null;
        }

        if ($startMeter <= 0 || $endMeter < $startMeter) {
            return null;
        }

        return round($endMeter - $startMeter, 2);
    }

    public static function calculatePlannedDiesel(float $expectedRunningKm, float $plannedMileage): ?float
    {
        if ($expectedRunningKm <= 0 || $plannedMileage <= 0) {
            return null;
        }

        return round($expectedRunningKm / $plannedMileage, 2);
    }

    public static function calculateExpectedTotalKm(float $plannedDiesel, float $plannedMileage): float
    {
        if ($plannedDiesel <= 0 || $plannedMileage <= 0) {
            return 0.0;
        }

        return round($plannedDiesel * $plannedMileage, 2);
    }

    public static function calculateRatePerKm(float $plannedDiesel, float $dieselRate, float $expectedTotalKm): ?float
    {
        if ($plannedDiesel <= 0 || $dieselRate <= 0 || $expectedTotalKm <= 0) {
            return null;
        }

        $totalTripCost = round($plannedDiesel * $dieselRate, 2);

        return round($totalTripCost / $expectedTotalKm, 2);
    }

    public static function calculateDifferenceAmount(float $unusedKm, float $ratePerKm): float
    {
        if ($unusedKm <= 0 || $ratePerKm <= 0) {
            return 0.0;
        }

        return round($unusedKm * $ratePerKm, 2);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function calculatePerformanceSummary(Trip $trip, ?float $actualTotalKm = null): ?array
    {
        $plannedMileage = (float) ($trip->planned_mileage_km_per_liter ?? 0);
        $plannedDiesel = (float) ($trip->planned_diesel_liters ?? 0);
        $dieselRate = (float) ($trip->diesel_rate_per_liter ?? 0);
        $actualKm = $actualTotalKm ?? (float) ($trip->total_distance_km ?? 0);

        if ($plannedMileage <= 0 || $plannedDiesel <= 0) {
            return null;
        }

        $expectedTotalKm = self::calculateExpectedTotalKm($plannedDiesel, $plannedMileage);
        $totalTripCost = $dieselRate > 0 ? round($plannedDiesel * $dieselRate, 2) : null;
        $ratePerKm = $totalTripCost !== null
            ? self::calculateRatePerKm($plannedDiesel, $dieselRate, $expectedTotalKm)
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
            ? round($extraKm / $plannedMileage, 2)
            : 0.0;
        $unusedDiesel = $unusedKm > 0
            ? round($unusedKm / $plannedMileage, 2)
            : 0.0;
        $extraDieselCost = $extraDiesel > 0 && $dieselRate > 0
            ? round($extraDiesel * $dieselRate, 2)
            : 0.0;
        $remainingAmount = self::calculateDifferenceAmount($unusedKm, $ratePerKm ?? 0.0);

        return [
            'calculation_version' => self::CALCULATION_VERSION,
            'planned_mileage_km_per_liter' => round($plannedMileage, 2),
            'planned_diesel_liters' => round($plannedDiesel, 2),
            'diesel_rate_per_liter' => $dieselRate > 0 ? round($dieselRate, 2) : null,
            'total_trip_cost' => $totalTripCost,
            'expected_total_km' => $expectedTotalKm,
            'actual_total_km' => $hasActual ? round($actualKm, 2) : null,
            'rate_per_km' => $ratePerKm,
            'unused_km' => $hasActual ? round($unusedKm, 2) : null,
            'extra_km' => $hasActual ? $extraKm : null,
            'remaining_amount' => $hasActual ? $remainingAmount : null,
            'performance_status' => $performanceStatus,
            'unused_diesel_liters' => $hasActual ? $unusedDiesel : null,
            'extra_diesel_liters' => $hasActual ? $extraDiesel : null,
            'extra_diesel_cost' => $hasActual ? $extraDieselCost : null,
        ];
    }
}
