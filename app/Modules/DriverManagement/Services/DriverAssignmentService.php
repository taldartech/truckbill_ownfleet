<?php

namespace App\Modules\DriverManagement\Services;

use App\DriverAssignment;
use App\Modules\DriverManagement\Repositories\DriverAssignmentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DriverAssignmentService
{
    public function __construct(private readonly DriverAssignmentRepository $driverAssignmentRepository)
    {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->driverAssignmentRepository->paginate($perPage);
    }

    public function create(array $data): DriverAssignment
    {
        return $this->driverAssignmentRepository->create($data);
    }

    public function update(DriverAssignment $driverAssignment, array $data): DriverAssignment
    {
        return $this->driverAssignmentRepository->update($driverAssignment, $data);
    }

    public function delete(DriverAssignment $driverAssignment): void
    {
        $this->driverAssignmentRepository->delete($driverAssignment);
    }
}
