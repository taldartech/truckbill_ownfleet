<?php

namespace App\Modules\DriverManagement\Services;

use App\DriverAssignment;
use App\Modules\DriverManagement\Repositories\DriverAssignmentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class DriverAssignmentService
{
    public function __construct(private readonly DriverAssignmentRepository $driverAssignmentRepository) {}

    public function paginate(int $perPage = 15, ?string $vehicleId = null): LengthAwarePaginator
    {
        return $this->driverAssignmentRepository->paginate($perPage, $vehicleId);
    }

    public function create(array $data): DriverAssignment
    {
        $isPrimary = (bool) ($data['is_primary'] ?? true);
        $assignedFrom = Carbon::parse($data['assigned_from'])->toDateString();
        $assignedTo = isset($data['assigned_to']) && $data['assigned_to'] !== null
            ? Carbon::parse($data['assigned_to'])->toDateString()
            : null;

        if ($isPrimary) {
            $this->closeOpenPrimaryAssignment(
                $data['vehicle_id'],
                Carbon::parse($assignedFrom)->subDay()->toDateString(),
            );
            $this->closeDriverOpenPrimaryAssignments(
                $data['driver_id'],
                Carbon::parse($assignedFrom)->subDay()->toDateString(),
                $data['vehicle_id'],
            );
            $this->ensureVehicleHasNoPrimaryOverlap($data['vehicle_id'], $assignedFrom, $assignedTo);
            $this->ensureDriverHasNoPrimaryOverlap($data['driver_id'], $assignedFrom, $assignedTo);
        }

        return $this->driverAssignmentRepository->create($data);
    }

    public function update(DriverAssignment $driverAssignment, array $data): DriverAssignment
    {
        $merged = [
            'vehicle_id' => $data['vehicle_id'] ?? $driverAssignment->vehicle_id,
            'driver_id' => $data['driver_id'] ?? $driverAssignment->driver_id,
            'assigned_from' => $data['assigned_from'] ?? $driverAssignment->assigned_from?->toDateString(),
            'assigned_to' => array_key_exists('assigned_to', $data)
                ? $data['assigned_to']
                : $driverAssignment->assigned_to?->toDateString(),
            'is_primary' => array_key_exists('is_primary', $data)
                ? (bool) $data['is_primary']
                : (bool) $driverAssignment->is_primary,
        ];

        if ($merged['is_primary']) {
            $assignedFrom = Carbon::parse((string) $merged['assigned_from'])->toDateString();
            $assignedTo = $merged['assigned_to'] !== null
                ? Carbon::parse((string) $merged['assigned_to'])->toDateString()
                : null;

            $this->closeOpenPrimaryAssignment(
                (string) $merged['vehicle_id'],
                Carbon::parse($assignedFrom)->subDay()->toDateString(),
                $driverAssignment->id,
            );
            $this->closeDriverOpenPrimaryAssignments(
                (string) $merged['driver_id'],
                Carbon::parse($assignedFrom)->subDay()->toDateString(),
                (string) $merged['vehicle_id'],
                $driverAssignment->id,
            );
            $this->ensureVehicleHasNoPrimaryOverlap(
                (string) $merged['vehicle_id'],
                $assignedFrom,
                $assignedTo,
                $driverAssignment->id,
            );
            $this->ensureDriverHasNoPrimaryOverlap(
                (string) $merged['driver_id'],
                $assignedFrom,
                $assignedTo,
                $driverAssignment->id,
            );
        }

        return $this->driverAssignmentRepository->update($driverAssignment, $data);
    }

    public function delete(DriverAssignment $driverAssignment): void
    {
        $this->driverAssignmentRepository->delete($driverAssignment);
    }

    private function closeOpenPrimaryAssignment(string $vehicleId, string $assignedTo, ?string $ignoreAssignmentId = null): void
    {
        DriverAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where('is_primary', true)
            ->whereNull('assigned_to')
            ->whereDate('assigned_from', '<=', $assignedTo)
            ->when($ignoreAssignmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAssignmentId))
            ->update(['assigned_to' => $assignedTo]);
    }

    private function closeDriverOpenPrimaryAssignments(
        string $driverId,
        string $assignedTo,
        string $newVehicleId,
        ?string $ignoreAssignmentId = null,
    ): void {
        DriverAssignment::query()
            ->where('driver_id', $driverId)
            ->where('is_primary', true)
            ->whereNull('assigned_to')
            ->whereDate('assigned_from', '<=', $assignedTo)
            ->where('vehicle_id', '!=', $newVehicleId)
            ->when($ignoreAssignmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAssignmentId))
            ->update(['assigned_to' => $assignedTo]);
    }

    private function ensureVehicleHasNoPrimaryOverlap(
        string $vehicleId,
        string $assignedFrom,
        ?string $assignedTo = null,
        ?string $ignoreAssignmentId = null,
    ): void {
        $conflictExists = DriverAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where('is_primary', true)
            ->when($ignoreAssignmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAssignmentId))
            ->whereDate('assigned_from', '<=', $assignedTo ?? '9999-12-31')
            ->where(function ($query) use ($assignedFrom) {
                $query->whereNull('assigned_to')
                    ->orWhereDate('assigned_to', '>=', $assignedFrom);
            })
            ->exists();

        if ($conflictExists) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['A primary driver is already assigned for this truck in the selected period.'],
            ]);
        }
    }

    private function ensureDriverHasNoPrimaryOverlap(
        string $driverId,
        string $assignedFrom,
        ?string $assignedTo = null,
        ?string $ignoreAssignmentId = null,
    ): void {
        $conflictExists = DriverAssignment::query()
            ->where('driver_id', $driverId)
            ->where('is_primary', true)
            ->when($ignoreAssignmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAssignmentId))
            ->whereDate('assigned_from', '<=', $assignedTo ?? '9999-12-31')
            ->where(function ($query) use ($assignedFrom) {
                $query->whereNull('assigned_to')
                    ->orWhereDate('assigned_to', '>=', $assignedFrom);
            })
            ->exists();

        if ($conflictExists) {
            throw ValidationException::withMessages([
                'driver_id' => ['This driver is already assigned as primary on another truck in the selected period.'],
            ]);
        }
    }
}
