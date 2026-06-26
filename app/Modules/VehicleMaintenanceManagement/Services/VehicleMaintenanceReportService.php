<?php

namespace App\Modules\VehicleMaintenanceManagement\Services;

use App\Modules\VehicleMaintenanceManagement\Repositories\VehicleMaintenanceRepository;
use App\VehicleMaintenance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehicleMaintenanceReportService
{
    public function __construct(
        private readonly VehicleMaintenanceRepository $repository,
        private readonly VehicleMaintenanceService $maintenanceService,
    ) {}

    /**
     * @param  array{from?: string|null, to?: string|null, maintenance_service_type_id?: int|null}  $filters
     * @return list<array<string, mixed>>
     */
    public function costSummary(array $filters): array
    {
        $this->assertDateRange($filters);

        $rows = $this->repository->reportQuery($filters)
            ->join(
                'maintenance_service_types',
                'maintenance_service_types.id',
                '=',
                'vehicle_maintenances.maintenance_service_type_id',
            )
            ->select([
                'vehicle_maintenances.maintenance_service_type_id',
                'maintenance_service_types.code as service_type',
                'maintenance_service_types.name as service_type_label',
                DB::raw('COUNT(*) as service_count'),
                DB::raw('SUM(vehicle_maintenances.cost_amount) as total_cost'),
            ])
            ->groupBy(
                'vehicle_maintenances.maintenance_service_type_id',
                'maintenance_service_types.code',
                'maintenance_service_types.name',
            )
            ->orderBy('maintenance_service_types.name')
            ->get();

        $data = $rows->map(fn ($row): array => [
            'maintenance_service_type_id' => (int) $row->maintenance_service_type_id,
            'service_type' => (string) $row->service_type,
            'service_type_label' => (string) $row->service_type_label,
            'service_count' => (int) $row->service_count,
            'total_cost' => round((float) $row->total_cost, 2),
        ])->all();

        $data[] = [
            'service_type' => '_grand_total',
            'service_type_label' => 'Grand Total',
            'service_count' => collect($data)->sum('service_count'),
            'total_cost' => round(collect($data)->sum('total_cost'), 2),
        ];

        return $data;
    }

    /**
     * @param  array{from?: string|null, to?: string|null, vehicle_id?: string|null}  $filters
     * @return list<array<string, mixed>>
     */
    public function truckWiseCost(array $filters): array
    {
        $this->assertDateRange($filters);

        return $this->repository->reportQuery($filters)
            ->join('vehicles', 'vehicles.id', '=', 'vehicle_maintenances.vehicle_id')
            ->select([
                'vehicle_maintenances.vehicle_id',
                'vehicles.registration_number',
                DB::raw('COUNT(*) as service_count'),
                DB::raw('SUM(vehicle_maintenances.cost_amount) as total_cost'),
            ])
            ->groupBy('vehicle_maintenances.vehicle_id', 'vehicles.registration_number')
            ->orderByDesc('total_cost')
            ->get()
            ->map(fn ($row): array => [
                'vehicle_id' => $row->vehicle_id,
                'truck' => $row->registration_number,
                'service_count' => (int) $row->service_count,
                'total_cost' => round((float) $row->total_cost, 2),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dueServices(): array
    {
        $today = now()->toDateString();

        return $this->maintenanceService->dueServices()
            ->map(function (VehicleMaintenance $maintenance) use ($today): array {
                $vehicle = $maintenance->vehicle;
                $odometer = (float) ($vehicle?->odometer_km ?? 0);
                $dueKm = $maintenance->next_service_due_km !== null
                    ? (float) $maintenance->next_service_due_km
                    : null;
                $dueDate = $maintenance->next_service_due_date?->toDateString();

                $kmDue = $dueKm !== null && $odometer >= $dueKm;
                $dateDue = $dueDate !== null && $dueDate < $today;

                $reason = match (true) {
                    $kmDue && $dateDue => 'KM & Date',
                    $kmDue => 'KM',
                    $dateDue => 'Date',
                    default => '—',
                };

                return [
                    'vehicle_id' => $maintenance->vehicle_id,
                    'truck' => $vehicle?->registration_number,
                    'last_service_date' => $maintenance->service_date?->toDateString(),
                    'odometer_km' => $odometer,
                    'service_type' => $maintenance->maintenanceServiceType?->code,
                    'service_type_label' => $maintenance->maintenanceServiceType?->name,
                    'next_service_due_km' => $dueKm,
                    'next_service_due_date' => $dueDate,
                    'overdue_reason' => $reason,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     */
    private function assertDateRange(array $filters): void
    {
        if (empty($filters['from']) || empty($filters['to'])) {
            throw ValidationException::withMessages([
                'from' => 'Date range is required.',
                'to' => 'Date range is required.',
            ]);
        }
    }
}
