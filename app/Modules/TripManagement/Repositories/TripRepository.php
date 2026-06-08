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

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }

        if (! empty($filters['broker_id'])) {
            $query->where('broker_id', (int) $filters['broker_id']);
        }

        if (! empty($filters['customer_name'])) {
            $query->where('customer_name', 'like', '%'.$filters['customer_name'].'%');
        }

        if (! empty($filters['trip_number'])) {
            $query->where('trip_number', 'like', '%'.$filters['trip_number'].'%');
        }

        if (! empty($filters['vehicle_search'])) {
            $search = (string) $filters['vehicle_search'];
            $query->whereHas('vehicle', function ($vehicleQuery) use ($search): void {
                $vehicleQuery->where('registration_number', 'like', "%{$search}%")
                    ->orWhere('make', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['driver_search'])) {
            $search = (string) $filters['driver_search'];
            $query->whereHas('driver', fn ($driverQuery) => $driverQuery->where('name', 'like', "%{$search}%"));
        }

        return $query;
    }
}
