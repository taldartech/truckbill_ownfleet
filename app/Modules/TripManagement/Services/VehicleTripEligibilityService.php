<?php

namespace App\Modules\TripManagement\Services;

use App\DriverAssignment;
use App\Enums\TripStatus;
use App\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class VehicleTripEligibilityService
{
    public const REASON_VEHICLE_INACTIVE = 'VEHICLE_INACTIVE';

    public const REASON_NO_PRIMARY_DRIVER = 'NO_PRIMARY_DRIVER';

    public const REASON_DRIVER_INACTIVE = 'DRIVER_INACTIVE';

    public const REASON_OPEN_TRIP_EXISTS = 'OPEN_TRIP_EXISTS';

    public function __construct(
        private readonly TripValidationService $tripValidation,
    ) {}

    /**
     * @param  Builder<Vehicle>  $query
     */
    public function applySelectableFirstOrdering(
        Builder $query,
        ?string $tripStartDate = null,
        ?string $tripEndDate = null,
    ): void {
        $asOf = $tripStartDate !== null
            ? Carbon::parse($tripStartDate)->toDateString()
            : now()->toDateString();

        $hasActivePrimaryDriverSql = <<<'SQL'
EXISTS (
    SELECT 1
    FROM driver_assignments da
    INNER JOIN drivers d ON d.id = da.driver_id AND d.deleted_at IS NULL
    WHERE da.vehicle_id = vehicles.id
      AND da.is_primary = 1
      AND DATE(da.assigned_from) <= ?
      AND (da.assigned_to IS NULL OR DATE(da.assigned_to) > ?)
      AND d.is_active = 1
)
SQL;

        $bindings = [$asOf, $asOf];
        $overlapSql = '';

        if ($tripStartDate !== null) {
            $windowStart = Carbon::parse($tripStartDate)->toDateString();
            $windowEnd = $tripEndDate !== null
                ? Carbon::parse($tripEndDate)->toDateString()
                : $windowStart;

            $createdStatus = TripStatus::Pending->value;
            $inProgressStatus = TripStatus::InProgress->value;

            $overlapSql = <<<'SQL'

        WHEN EXISTS (
            SELECT 1
            FROM trips t
            WHERE t.vehicle_id = vehicles.id
              AND t.deleted_at IS NULL
              AND t.status IN (?, ?)
              AND DATE(t.start_at) <= ?
              AND (t.end_at IS NULL OR DATE(t.end_at) >= ?)
        ) THEN 0
SQL;

            $bindings = array_merge($bindings, [
                $createdStatus,
                $inProgressStatus,
                $windowEnd,
                $windowStart,
            ]);
        }

        $query->orderByRaw(
            <<<SQL
(
    CASE
        WHEN vehicles.is_active = 0 THEN 0
        WHEN NOT ({$hasActivePrimaryDriverSql}) THEN 0{$overlapSql}
        ELSE 1
    END
) DESC
SQL,
            $bindings,
        )->latest('vehicles.created_at');
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
    public function evaluate(
        Vehicle $vehicle,
        ?string $tripStartDate = null,
        ?string $tripEndDate = null,
    ): array {
        if (! $vehicle->is_active) {
            return $this->ineligible(self::REASON_VEHICLE_INACTIVE, 'Truck Inactive');
        }

        $asOf = $tripStartDate !== null
            ? Carbon::parse($tripStartDate)->toDateString()
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
                $tripStartDate !== null ? 'No Driver Assigned on Trip Date' : 'No Driver Assigned',
            );
        }

        $driver = $assignment->driver;

        if (! $driver->is_active) {
            return $this->ineligible(self::REASON_DRIVER_INACTIVE, 'Driver Inactive', $driver->id, $driver->name, 'inactive');
        }

        if ($tripStartDate !== null) {
            $overlappingTrip = $this->tripValidation->findOverlappingOpenTrip(
                vehicleId: $vehicle->id,
                startAt: $tripStartDate,
                endAt: $tripEndDate,
            );

            if ($overlappingTrip !== null) {
                return [
                    ...$this->ineligible(
                        self::REASON_OPEN_TRIP_EXISTS,
                        'Trip Already Scheduled',
                        $driver->id,
                        $driver->name,
                        'active',
                    ),
                    'open_trip_number' => $overlappingTrip->trip_number,
                ];
            }
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

    public function assertEligibleForTripCreation(
        Vehicle $vehicle,
        ?string $tripStartDate = null,
        ?string $tripEndDate = null,
    ): void {
        $result = $this->evaluate($vehicle, $tripStartDate, $tripEndDate);

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
