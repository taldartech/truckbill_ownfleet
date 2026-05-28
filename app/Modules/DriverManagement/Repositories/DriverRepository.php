<?php

namespace App\Modules\DriverManagement\Repositories;

use App\Driver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class DriverRepository
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Driver::query()->latest()->paginate($perPage);
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

    public function delete(Driver $driver): void
    {
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
