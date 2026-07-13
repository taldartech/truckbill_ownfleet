<?php

namespace App\Modules\VehicleMaintenanceManagement\Repositories;

use App\Support\MaintenanceDueStatus;
use App\Vehicle;
use App\VehicleMaintenance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class VehicleMaintenanceRepository
{
    /**
     * @return LengthAwarePaginator<int, VehicleMaintenance>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->baseQuery($filters)
            ->latest('service_date')
            ->latest('created_at')
            ->paginate($perPage);
    }

    /**
     * @return Collection<int, VehicleMaintenance>
     */
    public function listForVehicle(string $vehicleId, int $perPage): LengthAwarePaginator
    {
        return VehicleMaintenance::query()
            ->with([
                'vehicle:id,registration_number,is_active,odometer_km',
                'maintenanceServiceType:id,name,code',
                'createdBy:id,name',
                'updatedBy:id,name',
            ])
            ->where('vehicle_id', $vehicleId)
            ->latest('service_date')
            ->latest('created_at')
            ->paginate($perPage);
    }

    /**
     * @param  array{
     *     search?: string|null,
     *     vehicle_id?: string|null,
     *     maintenance_service_type_id?: int|null,
     *     overdue_reason?: string|null,
     *     from?: string|null,
     *     to?: string|null
     * }  $filters
     * @return Collection<int, VehicleMaintenance>
     */
    public function dueServices(array $filters = []): Collection
    {
        return $this->dueServicesQuery($filters, [
            'vehicle:id,registration_number,is_active,odometer_km',
            'maintenanceServiceType:id,name,code',
            'createdBy:id,name',
            'updatedBy:id,name',
        ])->orderBy('service_date')->get();
    }

    /**
     * Lean due-service list for dashboard/compliance alerts (no auditor relations).
     *
     * @return Collection<int, VehicleMaintenance>
     */
    public function dueServicesForAlerts(): Collection
    {
        return $this->dueServicesQuery([], [
            'vehicle:id,registration_number,is_active,odometer_km',
            'maintenanceServiceType:id,name,code',
        ])->orderBy('service_date')->get();
    }

    /**
     * @param  array{
     *     search?: string|null,
     *     vehicle_id?: string|null,
     *     maintenance_service_type_id?: int|null,
     *     overdue_reason?: string|null,
     *     from?: string|null,
     *     to?: string|null
     * }  $filters
     * @param  list<string>  $with
     * @return Builder<VehicleMaintenance>
     */
    private function dueServicesQuery(array $filters, array $with): Builder
    {
        $today = now()->toDateString();

        $query = VehicleMaintenance::query()
            ->with($with)
            ->whereIn('id', $this->latestRecordIdsSubquery())
            ->where(function (Builder $query) use ($today): void {
                $query
                    ->where(function (Builder $dueQuery) use ($today): void {
                        $dueQuery
                            ->whereNotNull('next_service_due_date')
                            ->whereDate('next_service_due_date', '<', $today);
                    })
                    ->orWhere(function (Builder $dueQuery): void {
                        $dueQuery
                            ->whereNotNull('next_service_due_km')
                            ->whereHas('vehicle', function (Builder $vehicleQuery): void {
                                $vehicleQuery->whereColumn('vehicles.odometer_km', '>=', 'vehicle_maintenances.next_service_due_km');
                            });
                    });
            });

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];

            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('service_provider', 'like', "%{$search}%")
                    ->orWhereHas('vehicle', fn (Builder $vehicleQuery) => $vehicleQuery
                        ->where('registration_number', 'like', "%{$search}%"))
                    ->orWhereHas('maintenanceServiceType', fn (Builder $typeQuery) => $typeQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['vehicle_id'])) {
            $query->where('vehicle_id', $filters['vehicle_id']);
        }

        if (! empty($filters['maintenance_service_type_id'])) {
            $query->where('maintenance_service_type_id', $filters['maintenance_service_type_id']);
        }

        if (! empty($filters['overdue_reason'])) {
            $this->applyOverdueReasonFilter($query, (string) $filters['overdue_reason'], $today);
        }

        if (! empty($filters['from'])) {
            $query
                ->whereNotNull('next_service_due_date')
                ->whereDate('next_service_due_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query
                ->whereNotNull('next_service_due_date')
                ->whereDate('next_service_due_date', '<=', $filters['to']);
        }

        return $query;
    }

    /**
     * @param  Builder<VehicleMaintenance>  $query
     */
    private function applyOverdueReasonFilter(Builder $query, string $reason, string $today): void
    {
        match ($reason) {
            'km' => $query
                ->whereNotNull('next_service_due_km')
                ->whereHas('vehicle', function (Builder $vehicleQuery): void {
                    $vehicleQuery->whereColumn('vehicles.odometer_km', '>=', 'vehicle_maintenances.next_service_due_km');
                }),
            'date' => $query
                ->whereNotNull('next_service_due_date')
                ->whereDate('next_service_due_date', '<', $today),
            'both' => $query
                ->whereNotNull('next_service_due_km')
                ->whereNotNull('next_service_due_date')
                ->whereDate('next_service_due_date', '<', $today)
                ->whereHas('vehicle', function (Builder $vehicleQuery): void {
                    $vehicleQuery->whereColumn('vehicles.odometer_km', '>=', 'vehicle_maintenances.next_service_due_km');
                }),
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public function latestRecordIdsSubquery(): array
    {
        return VehicleMaintenance::query()
            ->select('vehicle_maintenances.id')
            ->whereNull('vehicle_maintenances.deleted_at')
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('next_service_due_km')
                    ->orWhereNotNull('next_service_due_date');
            })
            ->whereRaw('vehicle_maintenances.id = (
                SELECT vm2.id
                FROM vehicle_maintenances AS vm2
                WHERE vm2.vehicle_id = vehicle_maintenances.vehicle_id
                  AND vm2.tenant_id = vehicle_maintenances.tenant_id
                  AND vm2.deleted_at IS NULL
                ORDER BY vm2.service_date DESC, vm2.created_at DESC
                LIMIT 1
            )')
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array{
     *     search?: string|null,
     *     vehicle_id?: string|null,
     *     maintenance_service_type_id?: int|null,
     *     from?: string|null,
     *     to?: string|null,
     *     due_status?: string|null,
     *     due_services?: string|null
     * }  $filters
     * @return Builder<VehicleMaintenance>
     */
    private function baseQuery(array $filters): Builder
    {
        $query = VehicleMaintenance::query()->with([
            'vehicle:id,registration_number,is_active,odometer_km',
            'maintenanceServiceType:id,name,code',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];

            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('service_provider', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('vehicle', fn (Builder $vehicleQuery) => $vehicleQuery
                        ->where('registration_number', 'like', "%{$search}%"))
                    ->orWhereHas('maintenanceServiceType', fn (Builder $typeQuery) => $typeQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['vehicle_id'])) {
            $query->where('vehicle_id', $filters['vehicle_id']);
        }

        if (! empty($filters['maintenance_service_type_id'])) {
            $query->where('maintenance_service_type_id', $filters['maintenance_service_type_id']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('service_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('service_date', '<=', $filters['to']);
        }

        if (! empty($filters['due_status'])) {
            $this->applyDueStatusFilter($query, (string) $filters['due_status']);
        }

        if (! empty($filters['due_services'])) {
            $this->applyDueServicesFilter($query, (string) $filters['due_services']);
        }

        return $query;
    }

    /**
     * @param  Builder<VehicleMaintenance>  $query
     */
    private function applyDueStatusFilter(Builder $query, string $status): void
    {
        match ($status) {
            MaintenanceDueStatus::DUE => $this->applyDueOnlyConstraint($query),
            MaintenanceDueStatus::UPCOMING => $this->applyUpcomingConstraint($query),
            MaintenanceDueStatus::NONE => $query
                ->whereNull('next_service_due_km')
                ->whereNull('next_service_due_date'),
            default => null,
        };
    }

    /**
     * @param  Builder<VehicleMaintenance>  $query
     */
    private function applyDueServicesFilter(Builder $query, string $filter): void
    {
        match ($filter) {
            'due' => $this->applyDueOnlyConstraint($query),
            'scheduled' => $query->where(function (Builder $builder): void {
                $builder
                    ->whereNotNull('next_service_due_km')
                    ->orWhereNotNull('next_service_due_date');
            }),
            'unscheduled' => $query
                ->whereNull('next_service_due_km')
                ->whereNull('next_service_due_date'),
            default => null,
        };
    }

    /**
     * @param  Builder<VehicleMaintenance>  $query
     */
    private function applyDueOnlyConstraint(Builder $query): void
    {
        $today = now()->toDateString();

        $query->where(function (Builder $builder) use ($today): void {
            $builder
                ->where(function (Builder $dueQuery) use ($today): void {
                    $dueQuery
                        ->whereNotNull('next_service_due_date')
                        ->whereDate('next_service_due_date', '<', $today);
                })
                ->orWhere(function (Builder $dueQuery): void {
                    $dueQuery
                        ->whereNotNull('next_service_due_km')
                        ->whereHas('vehicle', function (Builder $vehicleQuery): void {
                            $vehicleQuery->whereColumn(
                                'vehicles.odometer_km',
                                '>=',
                                'vehicle_maintenances.next_service_due_km',
                            );
                        });
                });
        });
    }

    /**
     * @param  Builder<VehicleMaintenance>  $query
     */
    private function applyUpcomingConstraint(Builder $query): void
    {
        $today = now()->toDateString();

        $query
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNotNull('next_service_due_km')
                    ->orWhereNotNull('next_service_due_date');
            })
            ->where(function (Builder $builder) use ($today): void {
                $builder
                    ->where(function (Builder $dateQuery) use ($today): void {
                        $dateQuery
                            ->whereNull('next_service_due_date')
                            ->orWhereDate('next_service_due_date', '>=', $today);
                    })
                    ->where(function (Builder $kmQuery): void {
                        $kmQuery
                            ->whereNull('next_service_due_km')
                            ->orWhereHas('vehicle', function (Builder $vehicleQuery): void {
                                $vehicleQuery->whereColumn(
                                    'vehicles.odometer_km',
                                    '<',
                                    'vehicle_maintenances.next_service_due_km',
                                );
                            })
                            ->orWhereDoesntHave('vehicle');
                    });
            });
    }

    /**
     * @param  array{
     *     from?: string|null,
     *     to?: string|null,
     *     maintenance_service_type_id?: int|null,
     *     vehicle_id?: string|null
     * }  $filters
     * @return Builder<VehicleMaintenance>
     */
    public function reportQuery(array $filters): Builder
    {
        return $this->baseQuery($filters);
    }

    public function findVehicle(string $vehicleId): ?Vehicle
    {
        return Vehicle::query()->withTrashed()->find($vehicleId);
    }
}
