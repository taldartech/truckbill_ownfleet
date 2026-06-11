<?php

namespace App\Modules\DriverManagement\Services;

use App\Attendance;
use App\Driver;
use App\DriverAssignment;
use App\Enums\WagePaymentStatus;
use App\Modules\DriverManagement\Repositories\DriverAssignmentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DriverAssignmentService
{
    public function __construct(private readonly DriverAssignmentRepository $driverAssignmentRepository) {}

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
            $this->closeVehiclePrimaryAssignmentsBeforeNew(
                (string) $data['vehicle_id'],
                $assignedFrom,
            );
            $this->ensureDriverNotPrimaryOnOtherVehicle(
                (string) $data['driver_id'],
                (string) $data['vehicle_id'],
                $assignedFrom,
            );

            $data['assigned_to'] = null;
        }

        return $this->driverAssignmentRepository->create($data);
    }

    public function update(DriverAssignment $driverAssignment, array $data): DriverAssignment
    {
        return DB::transaction(function () use ($driverAssignment, $data): DriverAssignment {
            $this->assertAssignmentIsMutable($driverAssignment);

            return $this->updateAssignment($driverAssignment, $data);
        });
    }

    public function hasPaidAttendanceInPeriod(DriverAssignment $driverAssignment): bool
    {
        $assignedFrom = $driverAssignment->assigned_from?->toDateString();
        $assignedTo = $driverAssignment->assigned_to?->toDateString() ?? Carbon::today()->toDateString();

        if ($assignedFrom === null) {
            return false;
        }

        return Attendance::query()
            ->where('tenant_id', $driverAssignment->tenant_id)
            ->where('vehicle_id', $driverAssignment->vehicle_id)
            ->where(function ($query) use ($driverAssignment): void {
                $query->where('primary_driver_id', $driverAssignment->driver_id)
                    ->orWhere('wage_owner_driver_id', $driverAssignment->driver_id);
            })
            ->whereDate('attendance_date', '>=', $assignedFrom)
            ->whereDate('attendance_date', '<=', $assignedTo)
            ->where('wage_payment_status', WagePaymentStatus::Paid->value)
            ->exists();
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

        if ($merged['is_primary']) {
            $assignedFrom = Carbon::parse((string) $merged['assigned_from'])->toDateString();
            $isOpenAssignment = $driverAssignment->assigned_to === null;

            if ((string) $merged['driver_id'] !== (string) $driverAssignment->driver_id) {
                $this->assertDriverIsActive((string) $merged['driver_id']);
            }

            if ($isOpenAssignment) {
                $this->closeVehiclePrimaryAssignmentsBeforeNew(
                    (string) $merged['vehicle_id'],
                    $assignedFrom,
                    $driverAssignment->id,
                );
                $this->ensureDriverNotPrimaryOnOtherVehicle(
                    (string) $merged['driver_id'],
                    (string) $merged['vehicle_id'],
                    $assignedFrom,
                    $driverAssignment->id,
                );

                $data['assigned_to'] = null;
            }
        }

        return $this->driverAssignmentRepository->update($driverAssignment, $data);
    }

    public function delete(DriverAssignment $driverAssignment): void
    {
        $this->assertAssignmentIsMutable($driverAssignment);
        $this->driverAssignmentRepository->delete($driverAssignment);
    }

    private function assertAssignmentIsMutable(DriverAssignment $driverAssignment): void
    {
        if ($this->hasPaidAttendanceInPeriod($driverAssignment)) {
            throw ValidationException::withMessages([
                'driver_assignment' => ['This assignment cannot be changed because wage payment has already been recorded in attendance for this driver.'],
            ]);
        }
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
    ): void {
        $conflictExists = DriverAssignment::query()
            ->where('driver_id', $driverId)
            ->where('vehicle_id', '!=', $vehicleId)
            ->where('is_primary', true)
            ->when($ignoreAssignmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAssignmentId))
            ->activePrimaryOn($assignedFrom)
            ->exists();

        if ($conflictExists) {
            throw ValidationException::withMessages([
                'driver_id' => ['This driver is already assigned as primary on another truck. Choose a different driver or remove that assignment first.'],
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

    private function normalizeDate(?string $asOfDate): string
    {
        return $asOfDate !== null
            ? Carbon::parse($asOfDate)->toDateString()
            : Carbon::today()->toDateString();
    }
}
