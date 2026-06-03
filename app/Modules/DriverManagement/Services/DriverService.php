<?php

namespace App\Modules\DriverManagement\Services;

use App\Driver;
use App\DriverAssignment;
use App\Modules\DriverManagement\Repositories\DriverRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class DriverService
{
    public function __construct(private readonly DriverRepository $driverRepository) {}

    public function paginate(int $perPage = 15, bool $activeOnly = false): LengthAwarePaginator
    {
        return $this->driverRepository->paginate($perPage, $activeOnly);
    }

    public function create(array $data): Driver
    {
        return $this->driverRepository->create($data);
    }

    public function update(Driver $driver, array $data): Driver
    {
        $updatedDriver = $this->driverRepository->update($driver, $data);

        if (array_key_exists('is_active', $data) && ! (bool) $data['is_active']) {
            $this->closeOpenAssignmentsForInactiveDriver($updatedDriver);
        }

        return $updatedDriver;
    }

    public function delete(Driver $driver): void
    {
        $this->driverRepository->delete($driver);
    }

    public function licenseExpiringWithinDays(int $days): Collection
    {
        return $this->driverRepository->licenseExpiringWithinDays($days);
    }

    private function closeOpenAssignmentsForInactiveDriver(Driver $driver): void
    {
        DriverAssignment::query()
            ->where('driver_id', $driver->id)
            ->whereNull('assigned_to')
            ->update([
                'assigned_to' => Carbon::today()->toDateString(),
                'updated_at' => now(),
            ]);
    }
}
