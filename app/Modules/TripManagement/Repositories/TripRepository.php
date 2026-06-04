<?php

namespace App\Modules\TripManagement\Repositories;

use App\Trip;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TripRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->with(['vehicle', 'driver', 'routeLegs'])
            ->latest('start_at')
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Trip
    {
        return Trip::query()->create($data);
    }

    public function update(Trip $trip, array $data): Trip
    {
        $trip->update($data);

        return $trip->fresh();
    }

    public function delete(Trip $trip): void
    {
        $trip->delete();
    }

    public function findWithDetails(string $id): ?Trip
    {
        return Trip::query()
            ->with(['vehicle', 'driver', 'routeLegs', 'expenses.routeLeg'])
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Trip>
     */
    public function filteredQuery(array $filters = []): Builder
    {
        $query = Trip::query();

        if (! empty($filters['vehicle_id'])) {
            $query->where('vehicle_id', $filters['vehicle_id']);
        }

        if (! empty($filters['driver_id'])) {
            $query->where('driver_id', $filters['driver_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', strtoupper((string) $filters['status']));
        }

        if (! empty($filters['from_date'])) {
            $query->whereDate('start_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->whereDate('start_at', '<=', $filters['to_date']);
        }

        if (! empty($filters['customer_name'])) {
            $query->where('customer_name', 'like', '%'.$filters['customer_name'].'%');
        }

        return $query;
    }
}
