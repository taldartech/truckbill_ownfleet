<?php

namespace App\Modules\TripManagement\Services;

use App\DriverAssignment;
use App\Enums\TripStatus;
use App\Trip;
use App\Vehicle;
use Illuminate\Support\Carbon;

class VehicleTripEligibilityService
{
    public const REASON_VEHICLE_INACTIVE = 'VEHICLE_INACTIVE';

    public const REASON_NO_PRIMARY_DRIVER = 'NO_PRIMARY_DRIVER';

    public const REASON_DRIVER_INACTIVE = 'DRIVER_INACTIVE';

    public const REASON_OPEN_TRIP_EXISTS = 'OPEN_TRIP_EXISTS';

    /**
     * @return array{
     *     selectable: bool,
     *     reason_code: string|null,
     *     reason_label: string|null,
     *     primary_driver_id: string|null,
     *     primary_driver_name: string|null,
     *     driver_status: string|null,
     * }
     */
    public function evaluate(Vehicle $vehicle, ?string $asOfDate = null): array
    {
        if (! $vehicle->is_active) {
            return $this->ineligible(self::REASON_VEHICLE_INACTIVE, 'Truck Inactive');
        }

        $asOf = $asOfDate !== null
            ? Carbon::parse($asOfDate)->toDateString()
            : now()->toDateString();

        $assignment = DriverAssignment::query()
            ->where('vehicle_id', $vehicle->id)
            ->activePrimaryOn($asOf)
            ->with('driver')
            ->latest('assigned_from')
            ->first();

        if ($assignment === null || $assignment->driver === null) {
            return $this->ineligible(
                self::REASON_NO_PRIMARY_DRIVER,
                $asOfDate !== null ? 'No Driver Assigned on Trip Date' : 'No Driver Assigned',
            );
        }

        $driver = $assignment->driver;

        if (! $driver->is_active) {
            return $this->ineligible(self::REASON_DRIVER_INACTIVE, 'Driver Inactive', $driver->id, $driver->name, 'inactive');
        }

        $hasOpenTrip = Trip::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('status', [
                TripStatus::Created->value,
                TripStatus::InProgress->value,
            ])
            ->first();

        if ($hasOpenTrip !== null) {
            return [
                ...$this->ineligible(
                    self::REASON_OPEN_TRIP_EXISTS,
                    'Open Trip Exists',
                    $driver->id,
                    $driver->name,
                    'active',
                ),
                'open_trip_number' => $hasOpenTrip->trip_number,
            ];
        }

        return [
            'selectable' => true,
            'reason_code' => null,
            'reason_label' => null,
            'primary_driver_id' => $driver->id,
            'primary_driver_name' => $driver->name,
            'driver_status' => 'active',
            'open_trip_number' => null,
        ];
    }

    public function assertEligibleForTripCreation(Vehicle $vehicle, ?string $asOfDate = null): void
    {
        $result = $this->evaluate($vehicle, $asOfDate);

        if ($result['selectable']) {
            return;
        }

        $field = match ($result['reason_code']) {
            self::REASON_OPEN_TRIP_EXISTS => 'vehicle_id',
            self::REASON_DRIVER_INACTIVE => 'driver_id',
            default => 'vehicle_id',
        };

        throw \Illuminate\Validation\ValidationException::withMessages([
            $field => [$result['reason_label'] ?? 'This truck cannot be used for a new trip.'],
        ]);
    }

    /**
     * @return array{
     *     selectable: bool,
     *     reason_code: string|null,
     *     reason_label: string|null,
     *     primary_driver_id: string|null,
     *     primary_driver_name: string|null,
     *     driver_status: string|null,
     * }
     */
    private function ineligible(
        string $reasonCode,
        string $reasonLabel,
        ?string $driverId = null,
        ?string $driverName = null,
        ?string $driverStatus = null,
    ): array {
        return [
            'selectable' => false,
            'reason_code' => $reasonCode,
            'reason_label' => $reasonLabel,
            'primary_driver_id' => $driverId,
            'primary_driver_name' => $driverName,
            'driver_status' => $driverStatus,
            'open_trip_number' => null,
        ];
    }
}
