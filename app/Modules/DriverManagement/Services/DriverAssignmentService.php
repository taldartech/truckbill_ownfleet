<?php

namespace App\Modules\DriverManagement\Services;

use App\DriverAssignment;
use App\Modules\DriverManagement\Repositories\DriverAssignmentRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

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

        if ($isPrimary) {
            $this->closeOpenPrimaryAssignment(
                $data['vehicle_id'],
                Carbon::parse($data['assigned_from'])->subDay()->toDateString(),
            );
        }

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

    private function closeOpenPrimaryAssignment(string $vehicleId, string $assignedTo): void
    {
        DriverAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where('is_primary', true)
            ->whereNull('assigned_to')
            ->update(['assigned_to' => $assignedTo]);
    }
}
