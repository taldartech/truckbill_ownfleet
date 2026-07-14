<?php

namespace App\Modules\DriverManagement\Repositories;

use App\DriverAssignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DriverAssignmentRepository
{
    public function paginate(int $perPage = 15, ?string $vehicleId = null): LengthAwarePaginator
    {
        return DriverAssignment::query()
            ->with(['driver', 'vehicle'])
            ->when($vehicleId !== null, fn ($query) => $query->where('vehicle_id', $vehicleId))
            ->orderByRaw('CASE WHEN assigned_to IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByDesc('assigned_from')
            ->paginate($perPage);
    }

    public function create(array $data): DriverAssignment
    {
        return DriverAssignment::query()->create($data);
    }

    public function update(DriverAssignment $driverAssignment, array $data): DriverAssignment
    {
        $driverAssignment->update($data);

        return $driverAssignment->fresh();
    }

    public function delete(DriverAssignment $driverAssignment): void
    {
        $driverAssignment->delete();
    }
}
