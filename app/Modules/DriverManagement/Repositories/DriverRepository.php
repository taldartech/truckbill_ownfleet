<?php

namespace App\Modules\DriverManagement\Repositories;

use App\Driver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DriverRepository
{
    public function paginate(
        int $perPage = 15,
        bool $activeOnly = false,
        ?string $search = null,
        ?string $availableOnDate = null,
        ?string $excludeVehicleId = null,
        ?string $excludeAssignmentId = null
    ): LengthAwarePaginator {
        return Driver::query()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('license_number', 'like', $term)
                        ->orWhere('address', 'like', $term);
                });
            })
            ->when($availableOnDate !== null, function ($query) use ($availableOnDate, $excludeVehicleId, $excludeAssignmentId): void {
                $query->where('is_active', true)
                    ->where('joining_date', '<=', $availableOnDate)
                    ->whereDoesntHave('assignments', function ($q) use ($availableOnDate, $excludeVehicleId, $excludeAssignmentId): void {
                        $q->where('is_primary', true)
                            ->when($excludeVehicleId !== null, fn ($inner) => $inner->where('vehicle_id', '!=', $excludeVehicleId))
                            ->when($excludeAssignmentId !== null, fn ($inner) => $inner->where('id', '!=', $excludeAssignmentId))
                            ->whereDate('assigned_from', '<=', $availableOnDate)
                            ->where(function ($sub) use ($availableOnDate): void {
                                $sub->whereNull('assigned_to')
                                    ->orWhereDate('assigned_to', '>=', $availableOnDate);
                            });
                    });
            })
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function create(array $data): Driver
    {
        return Driver::query()->create($data);
    }

    public function update(Driver $driver, array $data): Driver
    {
        $driver->update($data);

        return $driver->fresh();
    }

    public function delete(Driver $driver, ?string $userId = null): void
    {
        $driver->update([
            'is_active' => false,
            'deleted_by' => $userId,
        ]);

        $driver->delete();
    }

    public function licenseExpiringWithinDays(int $days): Collection
    {
        return Driver::query()
            ->whereNotNull('license_expiry_date')
            ->whereDate('license_expiry_date', '<=', now()->addDays($days))
            ->orderBy('license_expiry_date')
            ->get();
    }
}
