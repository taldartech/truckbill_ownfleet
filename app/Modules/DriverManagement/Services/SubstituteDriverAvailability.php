<?php

namespace App\Modules\DriverManagement\Services;

use App\Attendance;
use App\DriverAssignment;
use App\Support\CurrentTenant;
use Illuminate\Validation\ValidationException;

class SubstituteDriverAvailability
{
    public function assertAvailable(string $date, string $substituteDriverId, ?string $exceptAttendanceId = null): void
    {
        $message = $this->conflictMessage($date, $substituteDriverId, $exceptAttendanceId);

        if ($message !== null) {
            throw ValidationException::withMessages([
                'temporary_driver_id' => [$message],
            ]);
        }
    }

    public function conflictMessage(string $date, string $substituteDriverId, ?string $exceptAttendanceId = null): ?string
    {
        $tenantId = CurrentTenant::requireId();

        $primaryAssignment = DriverAssignment::query()
            ->where('tenant_id', $tenantId)
            ->where('driver_id', $substituteDriverId)
            ->where('is_primary', true)
            ->whereDate('assigned_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('assigned_to')
                    ->orWhereDate('assigned_to', '>=', $date);
            })
            ->with('vehicle:id,registration_number')
            ->first();

        if ($primaryAssignment !== null) {
            $vehicleLabel = $primaryAssignment->vehicle?->registration_number ?? 'another truck';

            return "This driver is already assigned as primary on {$vehicleLabel} for this date. Choose a driver who is not assigned to any truck.";
        }

        $substituteAttendance = Attendance::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('attendance_date', $date)
            ->where('temporary_driver_id', $substituteDriverId)
            ->when($exceptAttendanceId !== null, fn ($query) => $query->whereKeyNot($exceptAttendanceId))
            ->with('vehicle:id,registration_number')
            ->first();

        if ($substituteAttendance !== null) {
            $vehicleLabel = $substituteAttendance->vehicle?->registration_number ?? 'another truck';

            return "This driver is already working as substitute on {$vehicleLabel} for this date.";
        }

        return null;
    }
}
