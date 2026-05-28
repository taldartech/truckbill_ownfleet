<?php

namespace App\Modules\DriverManagement\Services;

use App\Driver;
use App\Modules\DriverManagement\Repositories\DriverRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DriverService
{
    public function __construct(private readonly DriverRepository $driverRepository)
    {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->driverRepository->paginate($perPage);
    }

    public function create(array $data): Driver
    {
        return $this->driverRepository->create($data);
    }

    public function update(Driver $driver, array $data): Driver
    {
        return $this->driverRepository->update($driver, $data);
    }

    public function delete(Driver $driver): void
    {
        $this->driverRepository->delete($driver);
    }

    public function licenseExpiringWithinDays(int $days): Collection
    {
        return $this->driverRepository->licenseExpiringWithinDays($days);
    }
}
