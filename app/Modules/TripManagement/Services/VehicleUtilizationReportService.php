<?php

namespace App\Modules\TripManagement\Services;

use App\DriverAssignment;
use App\Enums\TripStatus;
use App\Modules\TripManagement\Support\TripReportScope;
use App\Modules\VehicleMaintenanceManagement\Services\VehicleMaintenanceService;
use App\Support\CurrentTenant;
use App\Tenant;
use App\Trip;
use App\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VehicleUtilizationReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     period: array{from_date: string, to_date: string, period_days: int},
     *     company_name: string,
     *     summary: array<string, mixed>,
     *     rows: list<array<string, mixed>>
     * }
     */
    public function build(array $filters = []): array
    {
        $today = Carbon::today()->toDateString();
        $fromDate = ! empty($filters['from_date'])
            ? (string) $filters['from_date']
            : Carbon::today()->startOfMonth()->toDateString();
        $toDate = ! empty($filters['to_date'])
            ? (string) $filters['to_date']
            : $today;

        $startDate = Carbon::parse($fromDate)->startOfDay();
        $endDate = Carbon::parse($toDate)->endOfDay();
        $periodDays = max(1, (int) Carbon::parse($fromDate)->diffInDays(Carbon::parse($toDate)) + 1);

        $now = Carbon::now();

        // 1. Identify vehicles due for maintenance
        $maintenanceVehicleIds = [];
        try {
            $dueServices = app(VehicleMaintenanceService::class)->dueServicesForAlerts();
            $maintenanceVehicleIds = $dueServices->pluck('vehicle_id')->unique()->filter()->values()->all();
        } catch (\Throwable) {
            $maintenanceVehicleIds = [];
        }

        // 2. Fetch all vehicles with current primary driver assignment and trips
        $vehicles = Vehicle::query()
            ->with([
                'currentPrimaryAssignment.driver:id,name',
                'trips' => function ($query) {
                    $query->with([
                        'driver:id,name',
                        'startStation:id,name',
                        'routeLegs' => function ($legQuery) {
                            $legQuery->with('toStation:id,name')->orderBy('sequence_no');
                        },
                    ])->whereIn('status', [
                        TripStatus::InProgress->value,
                        TripStatus::Completed->value,
                        TripStatus::Invoiced->value,
                    ])->orderByDesc('start_at');
                },
            ])
            ->orderBy('registration_number')
            ->get();

        // 3. Compute business generated & operational status per vehicle
        $rawRows = [];
        $summary = [
            'total_fleet' => $vehicles->count(),
            'on_trip_count' => 0,
            'available_count' => 0,
            'idle_count' => 0,
            'idle_7plus_count' => 0,
            'maintenance_count' => 0,
            'inactive_count' => 0,
            'total_trips' => 0,
            'total_running_days' => 0,
            'total_available_days' => 0,
            'total_revenue' => 0.0,
            'total_expenses' => 0.0,
            'total_profit' => 0.0,
            'total_km' => 0.0,
            'overall_utilization_rate' => 0.0,
        ];

        foreach ($vehicles as $vehicle) {
            $isActive = (bool) $vehicle->is_active;
            $hasDueMaintenance = in_array($vehicle->id, $maintenanceVehicleIds, true);

            // A. Find In-Progress Trip (if any)
            $inProgressTrip = $vehicle->trips->first(
                fn (Trip $t) => ($t->status?->value ?? (string) $t->status) === TripStatus::InProgress->value
            );

            // B. Find Latest Completed or Invoiced Trip
            $lastCompletedTrip = $vehicle->trips->first(
                fn (Trip $t) => in_array($t->status?->value ?? (string) $t->status, [
                    TripStatus::Completed->value,
                    TripStatus::Invoiced->value,
                ], true)
            );

            // C. Determine Current/Last Driver
            $driverName = null;
            if ($inProgressTrip && $inProgressTrip->driver) {
                $driverName = $inProgressTrip->driver->name;
            } elseif ($vehicle->currentPrimaryAssignment?->driver) {
                $driverName = $vehicle->currentPrimaryAssignment->driver->name;
            } elseif ($lastCompletedTrip && $lastCompletedTrip->driver) {
                $driverName = $lastCompletedTrip->driver->name;
            }

            // D. Determine Current/Last Location and Route
            $currentLocation = '—';
            $lastTripRoute = '—';

            if ($inProgressTrip) {
                $routeInfo = $this->extractTripRouteInfo($inProgressTrip);
                $currentLocation = $routeInfo['destination'] !== '—'
                    ? "En route to {$routeInfo['destination']}"
                    : ($routeInfo['route'] !== '—' ? $routeInfo['route'] : 'On Trip');
                $lastTripRoute = $routeInfo['route'];
            } elseif ($lastCompletedTrip) {
                $routeInfo = $this->extractTripRouteInfo($lastCompletedTrip);
                $currentLocation = $routeInfo['destination'] !== '—'
                    ? $routeInfo['destination']
                    : ($routeInfo['route'] !== '—' ? $routeInfo['route'] : '—');
                $lastTripRoute = $routeInfo['route'];
            }

            // E. Calculate Idle Days and Status
            $lastTripNumber = null;
            $lastTripId = null;
            $lastTripEndFormatted = null;
            $idleSinceFormatted = null;
            $idleDays = 0;
            $status = 'available';
            $statusLabel = 'Available';
            $statusTier = 'normal'; // 'running', 'normal', 'attention', 'prolonged', 'critical', 'maintenance', 'inactive'

            if ($inProgressTrip) {
                $status = 'on_trip';
                $statusLabel = 'On Trip';
                $statusTier = 'running';
                $idleDays = 0;
                $summary['on_trip_count']++;
                $lastTripNumber = $inProgressTrip->trip_number;
                $lastTripId = $inProgressTrip->id;
                $lastTripEndFormatted = 'In Progress';
                $idleSinceFormatted = '—';
            } elseif (! $isActive) {
                $status = 'inactive';
                $statusLabel = 'Inactive';
                $statusTier = 'inactive';
                $summary['inactive_count']++;

                if ($lastCompletedTrip) {
                    $lastTripEnd = $lastCompletedTrip->end_at ?? $lastCompletedTrip->actual_arrival_at ?? $lastCompletedTrip->start_at;
                    $idleSince = $lastTripEnd ? Carbon::parse($lastTripEnd) : Carbon::parse($vehicle->created_at);
                    $idleDays = max(0, (int) $idleSince->copy()->startOfDay()->diffInDays($now->copy()->startOfDay()));
                    $lastTripNumber = $lastCompletedTrip->trip_number;
                    $lastTripId = $lastCompletedTrip->id;
                    $lastTripEndFormatted = Carbon::parse($lastTripEnd)->format('d M Y');
                    $idleSinceFormatted = $idleSince->format('d M Y');
                } else {
                    $idleDays = max(0, (int) Carbon::parse($vehicle->created_at)->startOfDay()->diffInDays($now->copy()->startOfDay()));
                    $idleSinceFormatted = Carbon::parse($vehicle->created_at)->format('d M Y');
                }
            } elseif ($hasDueMaintenance) {
                $status = 'maintenance';
                $statusLabel = 'Maintenance Due';
                $statusTier = 'maintenance';
                $summary['maintenance_count']++;

                if ($lastCompletedTrip) {
                    $lastTripEnd = $lastCompletedTrip->end_at ?? $lastCompletedTrip->actual_arrival_at ?? $lastCompletedTrip->start_at;
                    $idleSince = $lastTripEnd ? Carbon::parse($lastTripEnd) : Carbon::parse($vehicle->created_at);
                    $idleDays = max(0, (int) $idleSince->copy()->startOfDay()->diffInDays($now->copy()->startOfDay()));
                    $lastTripNumber = $lastCompletedTrip->trip_number;
                    $lastTripId = $lastCompletedTrip->id;
                    $lastTripEndFormatted = Carbon::parse($lastTripEnd)->format('d M Y');
                    $idleSinceFormatted = $idleSince->format('d M Y');
                } else {
                    $idleDays = max(0, (int) Carbon::parse($vehicle->created_at)->startOfDay()->diffInDays($now->copy()->startOfDay()));
                    $idleSinceFormatted = Carbon::parse($vehicle->created_at)->format('d M Y');
                }
            } else {
                // Vehicle is Active and not on trip and not due maintenance
                if ($lastCompletedTrip) {
                    $lastTripEnd = $lastCompletedTrip->end_at ?? $lastCompletedTrip->actual_arrival_at ?? $lastCompletedTrip->start_at;
                    $idleSince = $lastTripEnd ? Carbon::parse($lastTripEnd) : Carbon::parse($vehicle->created_at);
                    $idleDays = max(0, (int) $idleSince->copy()->startOfDay()->diffInDays($now->copy()->startOfDay()));
                    $lastTripNumber = $lastCompletedTrip->trip_number;
                    $lastTripId = $lastCompletedTrip->id;
                    $lastTripEndFormatted = Carbon::parse($lastTripEnd)->format('d M Y');
                    $idleSinceFormatted = $idleSince->format('d M Y');
                } else {
                    $idleSince = Carbon::parse($vehicle->created_at);
                    $idleDays = max(0, (int) $idleSince->copy()->startOfDay()->diffInDays($now->copy()->startOfDay()));
                    $idleSinceFormatted = $idleSince->format('d M Y');
                }

                if ($idleDays <= 3) {
                    $status = 'available';
                    $statusLabel = 'Available';
                    $statusTier = 'normal';
                    $summary['available_count']++;
                } else {
                    $status = 'idle';
                    $summary['idle_count']++;
                    if ($idleDays >= 7) {
                        $summary['idle_7plus_count']++;
                    }

                    if ($idleDays <= 7) {
                        $statusLabel = 'Attention (4–7d)';
                        $statusTier = 'attention';
                    } elseif ($idleDays <= 15) {
                        $statusLabel = 'Prolonged (8–15d)';
                        $statusTier = 'prolonged';
                    } else {
                        $statusLabel = 'Critical (15+d)';
                        $statusTier = 'critical';
                    }
                }
            }

            // F. Calculate Business Generated in the Selected Date Range
            $periodTrips = $vehicle->trips->filter(function (Trip $trip) use ($fromDate, $toDate) {
                if (! $trip->start_at) {
                    return false;
                }
                $tripStartDate = Carbon::parse($trip->start_at)->toDateString();

                return $tripStartDate >= $fromDate && $tripStartDate <= $toDate;
            });

            $tripsCount = $periodTrips->count();
            $revenue = (float) $periodTrips->sum(fn (Trip $t) => (float) $t->total_freight_amount + (float) $t->total_custom_amount);
            $expenses = (float) $periodTrips->sum(fn (Trip $t) => (float) $t->total_expense_amount);
            $profit = round($revenue - $expenses, 2);
            $totalKm = (float) $periodTrips->sum(fn (Trip $t) => (float) $t->total_distance_km);

            // Compute running calendar days for this vehicle in period
            $runningDaySet = [];
            foreach ($periodTrips as $trip) {
                $tripStart = Carbon::parse($trip->start_at);
                $tripEnd = $trip->end_at ? Carbon::parse($trip->end_at) : ($now->copy()->lt($endDate) ? $now->copy() : $endDate);

                $dayCursor = $tripStart->copy()->max($startDate)->startOfDay();
                $dayLimit = $tripEnd->copy()->min($endDate)->startOfDay();

                while ($dayCursor->lte($dayLimit)) {
                    $runningDaySet[$dayCursor->toDateString()] = true;
                    $dayCursor->addDay();
                }
            }

            $runningDays = count($runningDaySet);
            $availableDays = $isActive ? $periodDays : 0;
            $utilizationRate = $availableDays > 0 ? round(($runningDays / $availableDays) * 100, 1) : 0.0;

            // Roll up totals
            $summary['total_trips'] += $tripsCount;
            $summary['total_running_days'] += $runningDays;
            $summary['total_available_days'] += $availableDays;
            $summary['total_revenue'] += $revenue;
            $summary['total_expenses'] += $expenses;
            $summary['total_profit'] += $profit;
            $summary['total_km'] += $totalKm;

            $rawRows[] = [
                'vehicle_id' => $vehicle->id,
                'registration_number' => $vehicle->registration_number,
                'vehicle_type' => $vehicle->vehicle_type,
                'is_active' => $isActive,
                'driver_name' => $driverName ?? '—',
                'current_location' => $currentLocation,
                'last_trip_id' => $lastTripId,
                'last_trip_number' => $lastTripNumber ?? '—',
                'last_trip_end' => $lastTripEndFormatted ?? '—',
                'last_trip_route' => $lastTripRoute,
                'idle_since' => $idleSinceFormatted ?? '—',
                'idle_days' => $idleDays,
                'status' => $status,
                'status_label' => $statusLabel,
                'status_tier' => $statusTier,
                'has_due_maintenance' => $hasDueMaintenance,
                'trips_count' => $tripsCount,
                'running_days' => $runningDays,
                'available_days' => $availableDays,
                'utilization_rate' => $utilizationRate,
                'revenue' => round($revenue, 2),
                'expenses' => round($expenses, 2),
                'profit' => round($profit, 2),
                'total_km' => round($totalKm, 2),
            ];
        }

        // Summary overall utilization
        $summary['overall_utilization_rate'] = $summary['total_available_days'] > 0
            ? round(($summary['total_running_days'] / $summary['total_available_days']) * 100, 1)
            : 0.0;
        $summary['total_revenue'] = round($summary['total_revenue'], 2);
        $summary['total_expenses'] = round($summary['total_expenses'], 2);
        $summary['total_profit'] = round($summary['total_profit'], 2);
        $summary['total_km'] = round($summary['total_km'], 2);

        // 4. Apply Filters (Search, Status, Vehicle, Idle Days)
        $filteredRows = $this->applyFilters($rawRows, $filters);

        // 5. Apply Serial Numbers
        $serial = 0;
        $finalRows = array_map(function (array $row) use (&$serial): array {
            $serial++;
            $row['s_no'] = $serial;

            return $row;
        }, $filteredRows);

        return [
            'period' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'period_days' => $periodDays,
            ],
            'company_name' => $this->resolveCompanyName(),
            'summary' => $summary,
            'rows' => $finalRows,
        ];
    }

    /**
     * Format rows for Excel export
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $filters = []): array
    {
        $report = $this->build($filters);

        return array_map(fn (array $r): array => [
            'S.No' => $r['s_no'],
            'Truck No.' => $r['registration_number'],
            'Driver' => $r['driver_name'],
            'Status' => $r['status_label'],
            'Current / Last Location' => $r['current_location'],
            'Last Trip No.' => $r['last_trip_number'],
            'Last Trip End' => $r['last_trip_end'],
            'Last Route' => $r['last_trip_route'],
            'Idle Since' => $r['idle_since'],
            'Idle Days' => $r['idle_days'],
            'Trips in Period' => $r['trips_count'],
            'Running Days' => $r['running_days'],
            'Utilization %' => $r['utilization_rate'].'%',
            'Revenue (INR)' => $r['revenue'],
            'Expenses (INR)' => $r['expenses'],
            'Net Profit (INR)' => $r['profit'],
            'Total KM' => $r['total_km'],
        ], $report['rows']);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function applyFilters(array $rows, array $filters): array
    {
        $search = ! empty($filters['search']) ? mb_strtolower(trim((string) $filters['search'])) : null;
        $statusFilter = ! empty($filters['status']) ? (string) $filters['status'] : null;
        $vehicleId = ! empty($filters['vehicle_id']) ? (string) $filters['vehicle_id'] : null;
        $minIdleDays = isset($filters['min_idle_days']) && $filters['min_idle_days'] !== '' ? (int) $filters['min_idle_days'] : null;

        return array_values(array_filter($rows, function (array $row) use ($search, $statusFilter, $vehicleId, $minIdleDays): bool {
            if ($vehicleId !== null && $row['vehicle_id'] !== $vehicleId) {
                return false;
            }

            if ($statusFilter !== null && $statusFilter !== 'all' && $statusFilter !== '') {
                if ($row['status'] !== $statusFilter) {
                    return false;
                }
            }

            if ($minIdleDays !== null && $minIdleDays > 0) {
                if ($row['idle_days'] < $minIdleDays) {
                    return false;
                }
            }

            if ($search !== null && $search !== '') {
                $searchable = mb_strtolower(implode(' ', [
                    $row['registration_number'],
                    $row['driver_name'],
                    $row['current_location'],
                    $row['last_trip_number'],
                    $row['last_trip_route'],
                    $row['status_label'],
                ]));

                if (! str_contains($searchable, $search)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Extract concise route representation from trip
     *
     * @return array{origin: string, destination: string, route: string}
     */
    private function extractTripRouteInfo(Trip $trip): array
    {
        $firstLeg = $trip->routeLegs->first();
        $lastLeg = $trip->routeLegs->last();

        $origin = '—';
        $destination = '—';

        if ($firstLeg) {
            $origin = $firstLeg->from_city ?: ($trip->startStation?->name ?? '—');
        } elseif ($trip->startStation) {
            $origin = $trip->startStation->name;
        }

        if ($lastLeg) {
            $destination = $lastLeg->to_city ?: ($lastLeg->toStation?->name ?? '—');
        }

        $route = '—';
        if ($origin !== '—' && $destination !== '—') {
            $route = "{$origin} → {$destination}";
        } elseif ($origin !== '—') {
            $route = $origin;
        } elseif ($destination !== '—') {
            $route = $destination;
        }

        return [
            'origin' => $origin,
            'destination' => $destination,
            'route' => $route,
        ];
    }

    private function resolveCompanyName(): string
    {
        $tenantId = CurrentTenant::id();

        if ($tenantId === null) {
            return '';
        }

        return Tenant::query()->find($tenantId)?->name ?? '';
    }
}
