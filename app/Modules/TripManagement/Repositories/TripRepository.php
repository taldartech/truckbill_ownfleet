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

    public function delete(Trip $trip, ?string $userId = null): void
    {
        if ($userId !== null) {
            $trip->update(['deleted_by' => $userId]);
        }

        $trip->delete();
    }

    public function findWithDetails(string $id): ?Trip
    {
        return Trip::query()
            ->with(['vehicle', 'driver', 'substituteDriver', 'routeLegs.customer', 'expenses.routeLeg'])
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

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }

        if (! empty($filters['broker_id'])) {
            $query->where('broker_id', (int) $filters['broker_id']);
        }

        if (! empty($filters['customer_name'])) {
            $query->where('customer_name', 'like', '%'.$filters['customer_name'].'%');
        }

        $hasTextSearch = $this->hasTextSearch($filters);

        if ($hasTextSearch) {
            $this->applyTextSearch($query, $filters);
        } else {
            if (! empty($filters['from_date'])) {
                $query->whereDate('start_at', '>=', $filters['from_date']);
            }

            if (! empty($filters['to_date'])) {
                $query->whereDate('start_at', '<=', $filters['to_date']);
            }
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function hasTextSearch(array $filters): bool
    {
        foreach (['search', 'trip_number', 'vehicle_search', 'driver_search'] as $key) {
            if (trim((string) ($filters[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyTextSearch(Builder $query, array $filters): void
    {
        if (trim((string) ($filters['search'] ?? '')) !== '') {
            $term = trim((string) $filters['search']);
            $query->where(function (Builder $searchQuery) use ($term): void {
                $searchQuery->where('trip_number', 'like', "%{$term}%")
                    ->orWhereHas('vehicle', function (Builder $vehicleQuery) use ($term): void {
                        $vehicleQuery->where('registration_number', 'like', "%{$term}%")
                            ->orWhere('make', 'like', "%{$term}%")
                            ->orWhere('model', 'like', "%{$term}%");
                    })
                    ->orWhereHas('driver', fn (Builder $driverQuery) => $driverQuery->where('name', 'like', "%{$term}%"));
            });

            return;
        }

        if (! empty($filters['trip_number'])) {
            $query->where('trip_number', 'like', '%'.$filters['trip_number'].'%');
        }

        if (! empty($filters['vehicle_search'])) {
            $search = (string) $filters['vehicle_search'];
            $query->whereHas('vehicle', function (Builder $vehicleQuery) use ($search): void {
                $vehicleQuery->where('registration_number', 'like', "%{$search}%")
                    ->orWhere('make', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['driver_search'])) {
            $search = (string) $filters['driver_search'];
            $query->whereHas('driver', fn (Builder $driverQuery) => $driverQuery->where('name', 'like', "%{$search}%"));
        }
    }
}
