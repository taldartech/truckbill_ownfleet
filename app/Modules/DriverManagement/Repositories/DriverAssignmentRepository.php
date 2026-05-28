<?php

namespace App\Modules\DriverManagement\Repositories;

use App\DriverAssignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DriverAssignmentRepository
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return DriverAssignment::query()->latest()->paginate($perPage);
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
