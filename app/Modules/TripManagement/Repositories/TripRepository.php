<?php

namespace App\Modules\TripManagement\Repositories;

use App\Enums\InvoicePaymentStatus;
use App\Modules\TripManagement\Support\TripReportScope;
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
        $archived = (string) ($filters['archived'] ?? 'active');

        $relations = ['vehicle', 'driver', 'substituteDriver', 'routeLegs'];

        if ($archived === 'archived') {
            $relations[] = 'archivedBy';
        }

        return $this->filteredQuery($filters)
            ->with($relations)
            ->withExists(['invoices as invoices_exists' => $this->activeInvoicesExistsConstraint()])
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

    public function archive(Trip $trip, ?string $userId = null, ?string $archiveReason = null): void
    {
        $trip->update([
            'archived_by' => $userId,
            'archive_reason' => $archiveReason,
        ]);

        $trip->delete();
    }

    public function restore(Trip $trip): void
    {
        $trip->restore();

        $trip->update([
            'archived_by' => null,
            'archive_reason' => null,
        ]);
    }

    /**
     * @deprecated Use archive()
     */
    public function delete(Trip $trip, ?string $userId = null, ?string $deletionReason = null): void
    {
        $this->archive($trip, $userId, $deletionReason);
    }

    public function findWithDetails(string $id): ?Trip
    {
        return Trip::query()
            ->with([
                'vehicle',
                'driver',
                'substituteDriver',
                'startStation.city',
                'routeLegs.customer',
                'routeLegs.pickup',
                'routeLegs.trip',
                'pickups.station',
                'pickups.drops.customer',
                'pickups.drops.pickup',
                'expenses.routeLeg',
                'statusHistories' => fn ($query) => $query->latest('changed_at'),
            ])
            ->withExists(['invoices as invoices_exists' => $this->activeInvoicesExistsConstraint()])
            ->find($id);
    }

    /**
     * @return \Closure(\Illuminate\Database\Eloquent\Builder<\App\Invoice>): void
     */
    private function activeInvoicesExistsConstraint(): \Closure
    {
        return function (Builder $query): void {
            $query->where('payment_status', '!=', InvoicePaymentStatus::Cancelled->value);
        };
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

        if (! empty($filters['trip_type'])) {
            $query->where('trip_type', strtoupper((string) $filters['trip_type']));
        }

        $archived = (string) ($filters['archived'] ?? 'active');

        if ($archived === 'archived') {
            $query->onlyTrashed();
        }

        if (! empty($filters['customer_name'])) {
            $query->where('customer_name', 'like', '%'.$filters['customer_name'].'%');
        }

        if ($this->hasTextSearch($filters)) {
            $this->applyTextSearch($query, $filters);
        }

        if (! empty($filters['from_date']) || ! empty($filters['to_date'])) {
            $this->applyDateRangeOverlap($query, $filters);
        }

        return $query;
    }

    /**
     * Report/dashboard query: Completed + Invoiced only, attributed by earlier of end_at / actual_arrival_at.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Trip>
     */
    public function reportFilteredQuery(array $filters = []): Builder
    {
        $listFilters = $filters;
        unset($listFilters['from_date'], $listFilters['to_date'], $listFilters['status']);

        $query = $this->filteredQuery($listFilters);

        $requestedStatus = ! empty($filters['status'])
            ? strtoupper((string) $filters['status'])
            : null;

        if ($requestedStatus !== null) {
            if (! in_array($requestedStatus, TripReportScope::reportableStatuses(), true)) {
                $query->whereRaw('0 = 1');
            } else {
                $query->where('status', $requestedStatus);
            }
        } else {
            TripReportScope::whereReportableStatuses($query);
        }

        $fromDate = ! empty($filters['from_date']) ? (string) $filters['from_date'] : null;
        $toDate = ! empty($filters['to_date']) ? (string) $filters['to_date'] : null;

        if ($fromDate !== null || $toDate !== null) {
            TripReportScope::whereReportDateInRange(
                $query,
                $fromDate ?? '1970-01-01',
                $toDate ?? now()->toDateString(),
            );
        } else {
            $expression = TripReportScope::reportDateExpression();
            $query->whereRaw("({$expression}) IS NOT NULL");
        }

        return $query;
    }

    /**
     * Include trips whose duration overlaps the selected date range (trip list filters).
     *
     * A trip from 20 Jun–30 Jun matches a filter of 21 Jun–25 Jun.
     * When end_at is null, the trip is treated as still open (ongoing),
     * so a trip starting 21 Jun with no end matches a filter from 22 Jun onward.
     *
     * @param  Builder<Trip>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyDateRangeOverlap(Builder $query, array $filters): void
    {
        $fromDate = ! empty($filters['from_date']) ? (string) $filters['from_date'] : null;
        $toDate = ! empty($filters['to_date']) ? (string) $filters['to_date'] : null;

        if ($toDate !== null) {
            $query->whereDate('start_at', '<=', $toDate);
        }

        if ($fromDate !== null) {
            $query->where(function (Builder $durationQuery) use ($fromDate): void {
                $durationQuery->whereNull('end_at')
                    ->orWhereDate('end_at', '>=', $fromDate);
            });
        }
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
                    ->orWhereHas('driver', fn (Builder $driverQuery) => $driverQuery->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('substituteDriver', fn (Builder $driverQuery) => $driverQuery->where('name', 'like', "%{$term}%"));
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
            $query->where(function (Builder $driverQuery) use ($search): void {
                $driverQuery->whereHas('driver', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('substituteDriver', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
            });
        }
    }
}
