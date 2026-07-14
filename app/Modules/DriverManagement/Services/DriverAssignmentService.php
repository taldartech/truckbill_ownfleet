<?php

namespace App\Modules\DriverManagement\Services;

use App\Driver;
use App\DriverAssignment;
use App\Modules\DriverManagement\Repositories\DriverAssignmentRepository;
use App\Trip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverAssignmentService
{
    public function __construct(
        private readonly DriverAssignmentRepository $driverAssignmentRepository,
    ) {}

    public function paginate(int $perPage = 15, ?string $vehicleId = null): LengthAwarePaginator
    {
        return $this->driverAssignmentRepository->paginate($perPage, $vehicleId);
    }

    public function currentPrimaryForVehicle(string $vehicleId, ?string $asOfDate = null): ?DriverAssignment
    {
        $asOf = $this->normalizeDate($asOfDate);

        return DriverAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->activePrimaryOn($asOf)
            ->with(['driver:id,name,phone,is_active'])
            ->latest('assigned_from')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function primaryAssignmentSummary(string $vehicleId, ?string $asOfDate = null): array
    {
        $asOf = $this->normalizeDate($asOfDate);
        $assignment = $this->currentPrimaryForVehicle($vehicleId, $asOf);

        return [
            'vehicle_id' => $vehicleId,
            'as_of_date' => $asOf,
            'has_active_primary' => $assignment !== null,
            'assignment' => $assignment !== null ? $this->formatAssignmentPayload($assignment, $asOf) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatAssignmentPayload(DriverAssignment $assignment, string $asOfDate): array
    {
        $assignment->loadMissing('driver:id,name,phone,is_active');

        return [
            'id' => $assignment->id,
            'driver_id' => $assignment->driver_id,
            'vehicle_id' => $assignment->vehicle_id,
            'assigned_from' => $assignment->assigned_from?->toDateString(),
            'assigned_to' => $assignment->assigned_to?->toDateString(),
            'is_primary' => (bool) $assignment->is_primary,
            'status' => $assignment->statusOn($asOfDate),
            'driver' => $assignment->driver !== null
                ? [
                    'id' => $assignment->driver->id,
                    'name' => $assignment->driver->name,
                    'phone' => $assignment->driver->phone,
                    'is_active' => (bool) $assignment->driver->is_active,
                ]
                : null,
        ];
    }

    public function create(array $data): DriverAssignment
    {
        return DB::transaction(function () use ($data): DriverAssignment {
            return $this->createAssignment($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createAssignment(array $data): DriverAssignment
    {
        $isPrimary = (bool) ($data['is_primary'] ?? true);
        $assignedFrom = Carbon::parse($data['assigned_from'])->toDateString();

        if ($isPrimary) {
            $this->assertDriverIsActive((string) $data['driver_id']);
            $this->assertAssignmentNotBeforeDriverJoiningDate((string) $data['driver_id'], $assignedFrom);
            $this->closeVehiclePrimaryAssignmentsBeforeNew(
                (string) $data['vehicle_id'],
                $assignedFrom,
            );
            $this->ensureDriverNotPrimaryOnOtherVehicle(
                (string) $data['driver_id'],
                (string) $data['vehicle_id'],
                $assignedFrom,
                null,
                null,
            );

            $data['assigned_to'] = null;
        }

        return $this->driverAssignmentRepository->create($data);
    }

    public function update(DriverAssignment $driverAssignment, array $data): DriverAssignment
    {
        return DB::transaction(function () use ($driverAssignment, $data): DriverAssignment {
            return $this->updateAssignment($driverAssignment, $data);
        });
    }


    /**
     * @param  array<string, mixed>  $data
     */
    private function updateAssignment(DriverAssignment $driverAssignment, array $data): DriverAssignment
    {
        $merged = [
            'vehicle_id' => $data['vehicle_id'] ?? $driverAssignment->vehicle_id,
            'driver_id' => $data['driver_id'] ?? $driverAssignment->driver_id,
            'assigned_from' => $data['assigned_from'] ?? $driverAssignment->assigned_from?->toDateString(),
            'is_primary' => array_key_exists('is_primary', $data)
                ? (bool) $data['is_primary']
                : (bool) $driverAssignment->is_primary,
        ];

        $assignedFrom = Carbon::parse((string) $merged['assigned_from'])->toDateString();
        $assignedTo = array_key_exists('assigned_to', $data)
            ? ($data['assigned_to'] !== null ? Carbon::parse((string) $data['assigned_to'])->toDateString() : null)
            : $driverAssignment->assigned_to?->toDateString();

        if ($merged['is_primary']) {
            $isOpenAssignment = $assignedTo === null;

            if ((string) $merged['driver_id'] !== (string) $driverAssignment->driver_id) {
                $this->assertDriverIsActive((string) $merged['driver_id']);
            }

            $this->assertAssignmentNotBeforeDriverJoiningDate((string) $merged['driver_id'], $assignedFrom);

            if ($isOpenAssignment) {
                $this->closeVehiclePrimaryAssignmentsBeforeNew(
                    (string) $merged['vehicle_id'],
                    $assignedFrom,
                    $driverAssignment->id,
                );

                $data['assigned_to'] = null;
            }

            $this->ensureDriverNotPrimaryOnOtherVehicle(
                (string) $merged['driver_id'],
                (string) $merged['vehicle_id'],
                $assignedFrom,
                $driverAssignment->id,
                $assignedTo,
            );
        }

        return $this->driverAssignmentRepository->update($driverAssignment, $data);
    }

    public function delete(DriverAssignment $driverAssignment): void
    {
        $this->assertAssignmentIsDeletable($driverAssignment);
        $this->driverAssignmentRepository->delete($driverAssignment);
    }

    private function assertAssignmentIsDeletable(DriverAssignment $driverAssignment): void
    {
        if ($this->isAssignmentActive($driverAssignment)) {
            throw ValidationException::withMessages([
                'driver_assignment' => ['This assignment is currently active and cannot be deleted. Use Remove Driver to close it instead.'],
            ]);
        }

        if (! $this->isAssignmentCompleted($driverAssignment)) {
            throw ValidationException::withMessages([
                'driver_assignment' => ['Only completed historical assignments can be deleted.'],
            ]);
        }

        if ($this->hasLinkedTrips($driverAssignment)) {
            throw ValidationException::withMessages([
                'driver_assignment' => ['This assignment cannot be deleted because one or more trips are linked to it.'],
            ]);
        }
    }

    private function isAssignmentActive(DriverAssignment $driverAssignment): bool
    {
        $asOfDate = Carbon::today()->toDateString();
        $assignedFrom = $driverAssignment->assigned_from?->toDateString();

        if ($assignedFrom === null || $assignedFrom > $asOfDate) {
            return false;
        }

        $assignedTo = $driverAssignment->assigned_to?->toDateString();

        return $assignedTo === null || $assignedTo > $asOfDate;
    }

    private function isAssignmentCompleted(DriverAssignment $driverAssignment): bool
    {
        $asOfDate = Carbon::today()->toDateString();
        $assignedFrom = $driverAssignment->assigned_from?->toDateString();
        $assignedTo = $driverAssignment->assigned_to?->toDateString();

        if ($assignedFrom === null || $assignedFrom > $asOfDate) {
            return false;
        }

        return $assignedTo !== null && $assignedTo <= $asOfDate;
    }

    private function hasLinkedTrips(DriverAssignment $driverAssignment): bool
    {
        $assignedFrom = $driverAssignment->assigned_from?->toDateString();
        $assignedTo = $driverAssignment->assigned_to?->toDateString();

        if ($assignedFrom === null) {
            return false;
        }

        $endDate = $assignedTo ?? $assignedFrom;

        return Trip::query()
            ->where('tenant_id', $driverAssignment->tenant_id)
            ->where('vehicle_id', $driverAssignment->vehicle_id)
            ->where('driver_id', $driverAssignment->driver_id)
            ->where(function ($query) use ($assignedFrom, $endDate): void {
                $query->where(function ($tripQuery) use ($assignedFrom, $endDate): void {
                    $tripQuery->whereDate('start_at', '>=', $assignedFrom)
                        ->whereDate('start_at', '<=', $endDate);
                })
                    ->orWhere(function ($tripQuery) use ($assignedFrom, $endDate): void {
                        $tripQuery->whereDate('end_at', '>=', $assignedFrom)
                            ->whereDate('end_at', '<=', $endDate);
                    })
                    ->orWhere(function ($tripQuery) use ($assignedFrom, $endDate): void {
                        $tripQuery->whereDate('start_at', '<=', $assignedFrom)
                            ->whereDate('end_at', '>=', $endDate);
                    });
            })
            ->exists();
    }

    public function removePrimaryDriver(string $vehicleId, ?string $actorId = null, ?string $asOfDate = null): void
    {
        DB::transaction(function () use ($vehicleId, $actorId, $asOfDate): void {
            $asOf = $this->normalizeDate($asOfDate);
            $assignment = $this->currentPrimaryForVehicle($vehicleId, $asOf);

            if ($assignment === null) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['No primary driver is currently assigned to this truck.'],
                ]);
            }

            $assignment->update([
                'assigned_to' => $asOf,
                'updated_by' => $actorId,
            ]);
        });
    }

    private function closeVehiclePrimaryAssignmentsBeforeNew(
        string $vehicleId,
        string $newAssignedFrom,
        ?string $ignoreAssignmentId = null,
    ): void {
        $newFrom = Carbon::parse($newAssignedFrom);

        $assignments = DriverAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where('is_primary', true)
            ->when($ignoreAssignmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAssignmentId))
            ->where(function ($query) use ($newFrom): void {
                $query->whereNull('assigned_to')
                    ->orWhereDate('assigned_to', '>=', $newFrom->toDateString());
            })
            ->get();

        foreach ($assignments as $assignment) {
            $from = $assignment->assigned_from;

            if ($from->lt($newFrom)) {
                $assignment->update([
                    'assigned_to' => $newFrom->copy()->subDay()->toDateString(),
                ]);

                continue;
            }

            $assignment->update([
                'assigned_to' => $from->toDateString(),
            ]);
        }
    }

    private function ensureDriverNotPrimaryOnOtherVehicle(
        string $driverId,
        string $vehicleId,
        string $assignedFrom,
        ?string $ignoreAssignmentId = null,
        ?string $assignedTo = null,
    ): void {
        $conflictExists = DriverAssignment::query()
            ->where('driver_id', $driverId)
            ->where('vehicle_id', '!=', $vehicleId)
            ->when($ignoreAssignmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAssignmentId))
            ->overlappingPrimaryPeriod($assignedFrom, $assignedTo)
            ->exists();

        if ($conflictExists) {
            throw ValidationException::withMessages([
                'driver_id' => ['This driver is already assigned as primary on another truck for overlapping dates. Choose a different driver or adjust the assignment dates first.'],
            ]);
        }
    }

    private function assertDriverIsActive(string $driverId): void
    {
        $driver = Driver::query()->find($driverId);

        if ($driver === null || ! $driver->is_active) {
            throw ValidationException::withMessages([
                'driver_id' => ['Selected driver is inactive or not found.'],
            ]);
        }
    }

    private function assertAssignmentNotBeforeDriverJoiningDate(string $driverId, string $assignedFrom): void
    {
        $driver = Driver::query()->find($driverId);

        if ($driver === null || $driver->joining_date === null) {
            return;
        }

        $joiningDate = $driver->joining_date->toDateString();

        if ($assignedFrom < $joiningDate) {
            throw ValidationException::withMessages([
                'assigned_from' => [
                    "Effective from date cannot be earlier than the driver's joining date ({$driver->joining_date->format('d M, Y')}).",
                ],
            ]);
        }
    }

    private function normalizeDate(?string $asOfDate): string
    {
        return $asOfDate !== null
            ? Carbon::parse($asOfDate)->toDateString()
            : Carbon::today()->toDateString();
    }
}
