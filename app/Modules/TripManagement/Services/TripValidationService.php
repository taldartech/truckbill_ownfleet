<?php

namespace App\Modules\TripManagement\Services;

use App\Driver;
use App\DriverAssignment;
use App\Enums\RouteType;
use App\Enums\TripStatus;
use App\Trip;
use App\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TripValidationService
{
    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_STATUS_TRANSITIONS = [
        'CREATED' => ['IN_PROGRESS'],
        'IN_PROGRESS' => ['COMPLETED', 'CANCELLED'],
        'COMPLETED' => [],
        'CANCELLED' => [],
    ];

    public function assertTripIsEditable(Trip $trip): void
    {
        $current = $trip->status instanceof TripStatus
            ? $trip->status->value
            : strtoupper((string) $trip->status);

        if (in_array($current, [TripStatus::Completed->value, TripStatus::Cancelled->value], true)) {
            throw ValidationException::withMessages([
                'trip' => ['Completed or cancelled trips cannot be edited.'],
            ]);
        }
    }

    public function findOverlappingOpenTrip(
        ?string $vehicleId = null,
        ?string $driverId = null,
        ?string $startAt = null,
        ?string $endAt = null,
        ?string $ignoreTripId = null,
    ): ?Trip {
        if ($startAt === null || ($vehicleId === null && $driverId === null)) {
            return null;
        }

        [$windowStart, $windowEnd] = $this->resolveTripWindow($startAt, $endAt);

        $query = Trip::query()
            ->whereIn('status', [
                TripStatus::Created->value,
                TripStatus::InProgress->value,
            ]);

        if ($vehicleId !== null) {
            $query->where('vehicle_id', $vehicleId);
        }

        if ($driverId !== null) {
            $query->where('driver_id', $driverId);
        }

        if ($ignoreTripId !== null) {
            $query->where('id', '!=', $ignoreTripId);
        }

        return $query
            ->whereRaw('DATE(start_at) <= ?', [$windowEnd])
            ->whereRaw('COALESCE(DATE(end_at), DATE(start_at)) >= ?', [$windowStart])
            ->first();
    }

    public function assertDriverAssignedToVehicle(string $vehicleId, string $driverId, ?string $tripStartAt): void
    {
        if ($tripStartAt === null) {
            throw ValidationException::withMessages([
                'start_at' => ['Trip start date is required to validate driver assignment.'],
            ]);
        }

        $date = Carbon::parse($tripStartAt)->toDateString();

        $assigned = DriverAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where('driver_id', $driverId)
            ->activePrimaryOn($date)
            ->exists();

        if (! $assigned) {
            throw ValidationException::withMessages([
                'driver_id' => ['No primary driver is assigned to this truck on the trip start date.'],
                'start_at' => ['Adjust the trip start date or update the truck\'s primary driver assignment.'],
            ]);
        }
    }


    public function assertDriverIsActive(string $driverId): void
    {
        $driver = Driver::query()->find($driverId);

        if ($driver === null || ! $driver->is_active) {
            throw ValidationException::withMessages([
                'driver_id' => ['Selected driver is inactive or not found.'],
            ]);
        }
    }

    public function assertVehicleExists(string $vehicleId): void
    {
        $vehicle = Vehicle::query()->find($vehicleId);

        if ($vehicle === null || ! $vehicle->is_active) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Selected truck is inactive or not found.'],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $routeLegs
     */
    public function assertRouteLegsAreValid(array $routeLegs, ?int $startStationId = null): void
    {
        if ($routeLegs === []) {
            throw ValidationException::withMessages([
                'route_legs' => ['Add at least one route stop for this round trip.'],
            ]);
        }

        if ($startStationId === null) {
            throw ValidationException::withMessages([
                'start_station_id' => ['Select the start station for this round trip.'],
            ]);
        }

        $hasRevenueLeg = false;
        $errors = [];
        $lastIndex = count($routeLegs) - 1;

        foreach ($routeLegs as $index => $leg) {
            $legNo = $index + 1;
            $fromStationId = isset($leg['from_station_id']) ? (int) $leg['from_station_id'] : null;
            $toStationId = isset($leg['to_station_id']) ? (int) $leg['to_station_id'] : null;

            if ($fromStationId === null) {
                $errors["route_legs.{$index}.from_station_id"] = ["Stop {$legNo}: from station is required."];
            }

            if ($toStationId === null) {
                $errors["route_legs.{$index}.to_station_id"] = ["Stop {$legNo}: to station is required."];
            }

            if ($fromStationId !== null && $toStationId !== null && $fromStationId === $toStationId) {
                $errors["route_legs.{$index}.to_station_id"] = ["Stop {$legNo}: from and to station cannot be the same."];
            }

            if ($index === 0 && $fromStationId !== null && $fromStationId !== $startStationId) {
                $errors['route_legs.0.from_station_id'] = ['First stop must start from the selected start station.'];
            }

            if ((float) ($leg['distance_km'] ?? 0) <= 0) {
                $errors["route_legs.{$index}.distance_km"] = ["Stop {$legNo}: distance must be greater than zero."];
            }

            $routeType = strtoupper((string) ($leg['route_type'] ?? RouteType::Loaded->value));

            if (in_array($routeType, [RouteType::Loaded->value, RouteType::HalfLoaded->value], true)) {
                $hasRevenueLeg = true;
                $freight = (float) ($leg['freight_amount'] ?? 0);
                $advance = (float) ($leg['advance_received'] ?? 0);

                if ($advance > $freight) {
                    $errors["route_legs.{$index}.advance_received"] = [
                        "Stop {$legNo}: advance cannot exceed freight amount.",
                    ];
                }
            }
        }

        $lastLeg = $routeLegs[$lastIndex] ?? null;
        $lastToStationId = isset($lastLeg['to_station_id']) ? (int) $lastLeg['to_station_id'] : null;

        if ($lastToStationId !== null && $lastToStationId !== $startStationId) {
            $errors['route_legs'] = ['Round trip must end at the start station. Add a return leg to complete the round.'];
        }

        if (! $hasRevenueLeg) {
            $errors['route_legs'] = ['At least one full load or half load stop is required.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assertStatusTransition(Trip $trip, string $newStatus, array $data = []): void
    {
        $current = $trip->status instanceof TripStatus
            ? $trip->status->value
            : strtoupper((string) $trip->status);

        $next = strtoupper($newStatus);

        if ($current === $next) {
            return;
        }

        $allowed = self::ALLOWED_STATUS_TRANSITIONS[$current] ?? [];

        if (! in_array($next, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot change trip status from {$current} to {$next}."],
            ]);
        }

        if ($next === TripStatus::Completed->value) {
            $endAt = $data['end_at'] ?? $trip->end_at;

            if ($endAt === null) {
                throw ValidationException::withMessages([
                    'end_at' => ['End date is required before marking a trip as completed.'],
                ]);
            }

            $actualArrivalAt = $data['actual_arrival_at'] ?? $trip->actual_arrival_at;

            if ($actualArrivalAt === null) {
                throw ValidationException::withMessages([
                    'actual_arrival_at' => ['Actual arrival date is required before marking a trip as completed.'],
                ]);
            }
        }

        if ($next === TripStatus::Cancelled->value) {
            $cancelledAt = $data['cancelled_at'] ?? $trip->cancelled_at;

            if ($cancelledAt === null) {
                throw ValidationException::withMessages([
                    'cancelled_at' => ['Cancellation date is required.'],
                ]);
            }

            $reason = trim((string) ($data['cancellation_reason'] ?? $trip->cancellation_reason ?? ''));

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'cancellation_reason' => ['Please provide a reason for cancelling this trip.'],
                ]);
            }
        }
    }

    public function assertNoDuplicateTripWindow(
        string $vehicleId,
        ?string $startAt,
        ?string $endAt,
        ?string $ignoreTripId = null,
    ): void {
        $overlap = $this->findOverlappingOpenTrip(
            vehicleId: $vehicleId,
            startAt: $startAt,
            endAt: $endAt,
            ignoreTripId: $ignoreTripId,
        );

        if ($overlap !== null) {
            throw ValidationException::withMessages([
                'start_at' => ['This truck already has an open trip in the selected date range. Complete or cancel it first.'],
                'vehicle_id' => ["Trip {$overlap->trip_number} is already scheduled for this truck on overlapping dates."],
            ]);
        }
    }

    public function assertNoDuplicateDriverTripWindow(
        string $driverId,
        ?string $startAt,
        ?string $endAt,
        ?string $ignoreTripId = null,
    ): void {
        $overlap = $this->findOverlappingOpenTrip(
            driverId: $driverId,
            startAt: $startAt,
            endAt: $endAt,
            ignoreTripId: $ignoreTripId,
        );

        if ($overlap !== null) {
            throw ValidationException::withMessages([
                'start_at' => ['This driver already has an open trip in the selected date range. Complete or cancel it first.'],
                'driver_id' => ["Trip {$overlap->trip_number} is already scheduled for this driver on overlapping dates."],
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveTripWindow(?string $startAt, ?string $endAt): array
    {
        $windowStart = Carbon::parse($startAt)->toDateString();
        $windowEnd = $endAt !== null
            ? Carbon::parse($endAt)->toDateString()
            : $windowStart;

        return [$windowStart, $windowEnd];
    }

    public function resolvePrimaryDriverIdForVehicle(string $vehicleId, ?string $asOfDate = null): ?string
    {
        $date = $asOfDate !== null
            ? Carbon::parse($asOfDate)->toDateString()
            : now()->toDateString();

        $assignment = DriverAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->activePrimaryOn($date)
            ->latest('assigned_from')
            ->first();

        return $assignment?->driver_id;
    }
}
