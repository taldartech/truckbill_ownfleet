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
        'CREATED' => ['IN_PROGRESS', 'CANCELLED'],
        'IN_PROGRESS' => ['COMPLETED', 'CANCELLED'],
        'COMPLETED' => [],
        'CANCELLED' => [],
    ];

    public function assertVehicleHasNoOpenTrip(string $vehicleId, ?string $ignoreTripId = null): void
    {
        $query = Trip::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', [
                TripStatus::Created->value,
                TripStatus::InProgress->value,
            ]);

        if ($ignoreTripId !== null) {
            $query->where('id', '!=', $ignoreTripId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['This vehicle already has an open trip. Complete or cancel it before creating another.'],
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
                'vehicle_id' => ['Selected vehicle is inactive or not found.'],
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

    public function assertStatusTransition(Trip $trip, string $newStatus): void
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

        if ($next === TripStatus::Completed->value && $trip->end_at === null) {
            throw ValidationException::withMessages([
                'end_at' => ['End date is required before marking a trip as completed.'],
            ]);
        }
    }

    public function assertNoDuplicateTripWindow(
        string $vehicleId,
        ?string $startAt,
        ?string $endAt,
        ?string $ignoreTripId = null,
    ): void {
        if ($startAt === null) {
            return;
        }

        $start = Carbon::parse($startAt);
        $end = $endAt !== null ? Carbon::parse($endAt) : $start->copy()->endOfDay();

        $query = Trip::query()
            ->where('vehicle_id', $vehicleId)
            ->where('status', '!=', TripStatus::Cancelled->value)
            ->where(function ($builder) use ($start, $end): void {
                $builder
                    ->whereBetween('start_at', [$start, $end])
                    ->orWhereBetween('end_at', [$start, $end])
                    ->orWhere(function ($overlap) use ($start, $end): void {
                        $overlap
                            ->where('start_at', '<=', $start)
                            ->where(function ($inner) use ($end): void {
                                $inner->whereNull('end_at')->orWhere('end_at', '>=', $end);
                            });
                    });
            });

        if ($ignoreTripId !== null) {
            $query->where('id', '!=', $ignoreTripId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_at' => ['Another trip already exists for this vehicle in the selected date range.'],
            ]);
        }
    }

    public function resolvePrimaryDriverIdForVehicle(string $vehicleId): ?string
    {
        $today = now()->toDateString();

        $assignment = DriverAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where('is_primary', true)
            ->whereDate('assigned_from', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('assigned_to')
                    ->orWhereDate('assigned_to', '>=', $today);
            })
            ->latest('assigned_from')
            ->first();

        return $assignment?->driver_id;
    }
}
