<?php

namespace App\Modules\DriverManagement\Services;

use App\DriverAssignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class DriverAvailabilityService
{
    /**
     * Calculate and attach availability to each driver in the paginator.
     */
    public function attachAvailability(
        LengthAwarePaginator $paginator,
        string $availableOnDate,
        ?string $excludeAssignmentId = null
    ): LengthAwarePaginator {
        $paginator->load(['assignments' => function ($query) use ($availableOnDate, $excludeAssignmentId) {
            $query->where('is_primary', true)
                ->when($excludeAssignmentId !== null, fn ($q) => $q->where('id', '!=', $excludeAssignmentId))
                ->whereDate('assigned_from', '>', $availableOnDate)
                ->orderBy('assigned_from', 'asc');
        }]);

        foreach ($paginator->items() as $driver) {
            $nextAssignment = $driver->assignments->first();
            $driver->setAttribute('availability', [
                'available_until' => $nextAssignment ? $nextAssignment->assigned_from->subDay()->toDateString() : null,
            ]);
        }

        return $paginator;
    }

    /**
     * Check if a proposed assignment is within driver availability limits.
     */
    public function assertAssignmentWithinAvailabilityLimits(
        string $driverId,
        string $assignedFrom,
        ?string $assignedTo,
        ?string $ignoreAssignmentId = null
    ): void {
        $nextAssignment = DriverAssignment::query()
            ->where('driver_id', $driverId)
            ->where('is_primary', true)
            ->when($ignoreAssignmentId !== null, fn ($query) => $query->whereKeyNot($ignoreAssignmentId))
            ->whereDate('assigned_from', '>', $assignedFrom)
            ->orderBy('assigned_from', 'asc')
            ->first();

        if ($nextAssignment !== null) {
            $availableUntil = $nextAssignment->assigned_from->subDay()->toDateString();

            if ($assignedTo === null) {
                throw ValidationException::withMessages([
                    'assigned_to' => [
                        "This driver has a subsequent assignment starting on {$nextAssignment->assigned_from->format('d M, Y')}. You must specify an Effective To date on or before {$availableUntil}.",
                    ],
                ]);
            }

            if ($assignedTo > $availableUntil) {
                throw ValidationException::withMessages([
                    'assigned_to' => [
                        "Effective To date cannot be later than the driver's available until date ({$availableUntil}) due to a subsequent assignment.",
                    ],
                ]);
            }
        }
    }
}
