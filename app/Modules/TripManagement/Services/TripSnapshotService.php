<?php

namespace App\Modules\TripManagement\Services;

use App\Modules\TripManagement\Support\TripFuelCalculator;
use App\Trip;
use App\Vehicle;

class TripSnapshotService
{
    /**
     * @param  Trip|array<string, mixed>  $trip
     */
    public function copyTruckDefaultsToTrip(Vehicle $vehicle, Trip|array &$trip): void
    {
        $this->copyMileage($vehicle, $trip);
        $this->copyMaxLoad($vehicle, $trip);
        $this->copyDiesel($trip);
    }

    /**
     * @param  Trip|array<string, mixed>  $trip
     */
    public function copyMileage(Vehicle $vehicle, Trip|array &$trip): void
    {
        $this->setTripValue($trip, 'planned_mileage_km_per_liter', (float) ($vehicle->default_mileage_km_per_liter ?? 0));
    }

    /**
     * @param  Trip|array<string, mixed>  $trip
     */
    public function copyMaxLoad(Vehicle $vehicle, Trip|array &$trip): void
    {
        $this->setTripValue($trip, 'max_load_ton', (float) ($vehicle->max_load_ton ?? 0));
    }

    /**
     * @param  Trip|array<string, mixed>  $trip
     */
    public function copyDiesel(Trip|array &$trip): void
    {
        $startMeter = $this->getTripValue($trip, 'start_meter_reading_km');
        $endMeter = $this->getTripValue($trip, 'end_meter_reading_km');
        $plannedMileage = (float) ($this->getTripValue($trip, 'planned_mileage_km_per_liter') ?? 0);

        $runningKm = TripFuelCalculator::calculateExpectedRunningKm(
            $startMeter !== null && $startMeter !== '' ? (float) $startMeter : null,
            $endMeter !== null && $endMeter !== '' ? (float) $endMeter : null,
        );

        if ($runningKm === null) {
            return;
        }

        $plannedDiesel = TripFuelCalculator::calculatePlannedDiesel($runningKm, $plannedMileage);

        if ($plannedDiesel === null) {
            return;
        }

        $this->setTripValue($trip, 'planned_diesel_liters', $plannedDiesel);
    }

    /**
     * @param  Trip|array<string, mixed>  $trip
     */
    private function getTripValue(Trip|array $trip, string $key): mixed
    {
        if ($trip instanceof Trip) {
            return $trip->{$key};
        }

        return $trip[$key] ?? null;
    }

    /**
     * @param  Trip|array<string, mixed>  $trip
     */
    private function setTripValue(Trip|array &$trip, string $key, mixed $value): void
    {
        if ($trip instanceof Trip) {
            $trip->{$key} = $value;

            return;
        }

        $trip[$key] = $value;
    }
}
