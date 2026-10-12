<?php

namespace App\Support;

use App\Driver;
use App\Enums\AttendanceStatus;
use App\Enums\InvoicePaymentStatus;
use App\Enums\TripExpenseType;
use App\Enums\TripStatus;
use App\Enums\TyreExpenseType;
use App\Enums\TyreStatus;
use App\Invoice;
use App\Modules\DriverManagement\Services\AttendanceCalendarService;
use App\Modules\TripManagement\Support\TripReportScope;
use App\Modules\TyreManagement\Services\TyreAlertService;
use App\Modules\VehicleMaintenanceManagement\Services\VehicleMaintenanceService;
use App\Trip;
use App\TripExpense;
use App\Tyre;
use App\TyreExpense;
use App\User;
use App\Vehicle;
use App\VehicleDocument;
use Illuminate\Support\Carbon;

class FleetDashboardStats
{
    private const DOCUMENT_EXPIRY_WINDOW_DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public static function build(User $user, ?string $fromDate = null, ?string $toDate = null): array
    {
        $today = Carbon::today()->toDateString();
        $fromDate = $fromDate ?? $today;
        $toDate = $toDate ?? $fromDate;

        $vehicleCounts = Vehicle::query()
            ->selectRaw('
                COUNT(*) as total_count,
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_count,
                COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_count
            ')
            ->first();

        $driverCounts = Driver::query()
            ->selectRaw('
                COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_count,
                COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_count
            ')
            ->first();

        $fleet = [
            'vehicles_count' => (int) ($vehicleCounts->total_count ?? 0),
            'active_vehicles_count' => (int) ($vehicleCounts->active_count ?? 0),
            'inactive_vehicles_count' => (int) ($vehicleCounts->inactive_count ?? 0),
            'drivers_count' => (int) ($driverCounts->active_count ?? 0),
            'inactive_drivers_count' => (int) ($driverCounts->inactive_count ?? 0),
        ];

        if ($user->hasPermission('attendances.view')) {
            $fleet = array_merge($fleet, self::buildAttendancePeriodStats($fromDate, $toDate));
        }

        if ($user->hasPermission('vehicle_documents.view')) {
            $windowEnd = Carbon::today()->addDays(self::DOCUMENT_EXPIRY_WINDOW_DAYS)->toDateString();
            $docCounts = VehicleDocument::query()
                ->whereNotNull('expiry_date')
                ->selectRaw('
                    COUNT(CASE WHEN expiry_date < ? THEN 1 END) as expired_count,
                    COUNT(CASE WHEN expiry_date >= ? AND expiry_date <= ? THEN 1 END) as expiring_count
                ', [$today, $today, $windowEnd])
                ->first();

            $fleet['expired_documents_count'] = (int) ($docCounts->expired_count ?? 0);
            $fleet['expiring_documents_count'] = (int) ($docCounts->expiring_count ?? 0);
        }

        if ($user->hasPermission('drivers.view')) {
            $fleet['license_expiring_count'] = Driver::query()
                ->where('is_active', true)
                ->whereNotNull('license_expiry_date')
                ->where('license_expiry_date', '>=', $today)
                ->where(
                    'license_expiry_date',
                    '<=',
                    Carbon::today()->addDays(self::DOCUMENT_EXPIRY_WINDOW_DAYS)->toDateString(),
                )
                ->count();
        }

        $payload = [
            'fleet' => $fleet,
            'period' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'generated_at' => ApiDateTime::toIso(now()),
        ];

        $tripFinancials = self::buildTripStats($fromDate, $toDate);
        $fuelStats = self::buildFuelStats($fromDate, $toDate);

        if ($user->hasPermission('trips.view')) {
            $payload['trips'] = $tripFinancials;
            $payload['fuel'] = $fuelStats;
        }

        if ($user->hasPermission('tyres.view')) {
            $payload['tyres'] = array_merge(
                self::buildTyreInventoryStats(),
                self::buildTyrePeriodStats($fromDate, $toDate),
                self::buildTyreAlertStats(),
            );
        }

        $fleetStatus = self::buildFleetStatusStats($fromDate, $toDate);
        $payload['fleet_status'] = $fleetStatus;
        $payload['fleet']['fleet_status'] = $fleetStatus;

        $outstandingAmount = 0.0;
        try {
            $outstandingAmount = (float) Invoice::query()
                ->whereNotIn('payment_status', [
                    InvoicePaymentStatus::Paid->value,
                    InvoicePaymentStatus::Cancelled->value,
                ])
                ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as outstanding')
                ->value('outstanding');
        } catch (\Throwable) {
            $outstandingAmount = 0.0;
        }

        $payload['kpis'] = [
            'total_fleet' => $fleetStatus['overview']['total_trucks'] ?? $fleet['vehicles_count'],
            'on_trip' => $fleetStatus['overview']['on_trip'] ?? 0,
            'available' => $fleetStatus['overview']['available'] ?? 0,
            'idle' => $fleetStatus['overview']['idle'] ?? 0,
            'maintenance' => $fleetStatus['overview']['maintenance'] ?? 0,
            'fleet_utilization' => $fleetStatus['utilization']['utilization_rate'] ?? 0,
            'revenue' => $tripFinancials['total_freight_amount'] ?? 0,
            'cost' => $tripFinancials['total_expense_amount'] ?? 0,
            'profit' => $tripFinancials['profit_amount'] ?? 0,
            'outstanding' => round((float) $outstandingAmount, 2),
            'avg_mileage' => $fuelStats['average_mileage'],
            'fuel_cost' => $fuelStats['total_fuel_cost'],
            'fuel_liters' => $fuelStats['total_fuel_liters'],
            'fuel_cost_per_km' => $fuelStats['cost_per_km'],
            'fuel_variance_percentage' => $fuelStats['fuel_variance_percentage'],
        ];

        return $payload;
    }

    /**
     * @return array<string, int>
     */
    private static function buildAttendancePeriodStats(string $fromDate, string $toDate): array
    {
        $overview = app(AttendanceCalendarService::class)->buildOverview($fromDate, $toDate);

        $present = 0;
        $absent = 0;
        $absentSubstitute = 0;
        $noWorkDay = 0;

        foreach ($overview['rows'] as $row) {
            foreach ($row['days'] as $cell) {
                if ($cell === null || empty($cell['status'])) {
                    continue;
                }

                $status = strtoupper((string) $cell['status']);

                if ($status === AttendanceStatus::Present->value) {
                    $present++;
                } elseif ($status === AttendanceStatus::NoWorkDay->value) {
                    $noWorkDay++;
                } elseif ($status === AttendanceStatus::Absent->value) {
                    if (! empty($cell['temporary_driver_id'])) {
                        $absentSubstitute++;
                    } else {
                        $absent++;
                    }
                }
            }
        }

        return [
            'assigned_trucks_today_count' => count($overview['rows']),
            'present_today_count' => $present,
            'absent_today_count' => $absent,
            'absent_substitute_today_count' => $absentSubstitute,
            'no_work_day_today_count' => $noWorkDay,
            'exceptions_today_count' => (int) ($overview['summary']['exceptions'] ?? 0),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private static function buildTripStats(string $fromDate, string $toDate): array
    {
        $financialQuery = Trip::query();
        TripReportScope::whereReportableStatuses($financialQuery);
        TripReportScope::whereReportDateInRange($financialQuery, $fromDate, $toDate);

        $row = (clone $financialQuery)
            ->selectRaw('
                COALESCE(SUM(total_freight_amount), 0) as total_freight,
                COALESCE(SUM(total_custom_amount), 0) as total_custom,
                COALESCE(SUM(total_expense_amount), 0) as total_expenses,
                COUNT(CASE WHEN status = ? THEN 1 END) as completed_count,
                COUNT(CASE WHEN status = ? THEN 1 END) as invoiced_count,
                COUNT(CASE WHEN status = ? THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = ? THEN 1 END) as in_progress_count
            ', [
                TripStatus::Completed->value,
                TripStatus::Invoiced->value,
                TripStatus::Pending->value,
                TripStatus::InProgress->value,
            ])
            ->first();

        $totalFreight = (float) ($row->total_freight ?? 0);
        $totalCustom = (float) ($row->total_custom ?? 0);
        $totalTripExpenses = (float) ($row->total_expenses ?? 0);
        $totalFreightWithCustom = $totalFreight + $totalCustom;

        $completedCount = (int) ($row->completed_count ?? 0);
        $invoicedCount = (int) ($row->invoiced_count ?? 0);
        $pendingCount = (int) ($row->pending_count ?? 0);
        $inProgressCount = (int) ($row->in_progress_count ?? 0);

        return [
            'total_count' => $pendingCount + $inProgressCount + $completedCount + $invoicedCount,
            'pending_count' => $pendingCount,
            'in_progress_count' => $inProgressCount,
            'completed_count' => $completedCount,
            'cancelled_count' => 0,
            'invoiced_count' => $invoicedCount,
            'total_freight_amount' => round($totalFreightWithCustom, 2),
            'total_custom_amount' => round($totalCustom, 2),
            'total_expense_amount' => round($totalTripExpenses, 2),
            'profit_amount' => round($totalFreightWithCustom - $totalTripExpenses, 2),
            'running_trips' => self::buildRunningTripsList(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildRunningTripsList(): array
    {
        return Trip::query()
            ->with(['vehicle:id,registration_number', 'driver:id,name'])
            ->where('status', TripStatus::InProgress->value)
            ->latest('start_at')
            ->limit(5)
            ->get()
            ->map(fn (Trip $trip): array => [
                'id' => $trip->id,
                'trip_number' => $trip->trip_number,
                'vehicle_registration' => $trip->vehicle?->registration_number,
                'driver_name' => $trip->driver?->name,
                'start_at' => ApiDateTime::toIso($trip->start_at),
            ])
            ->all();
    }

    /**
     * @return array<string, float|int>
     */
    private static function buildFuelStats(string $fromDate, string $toDate): array
    {
        $query = Trip::query();
        TripReportScope::whereReportableStatuses($query);
        TripReportScope::whereReportDateInRange($query, $fromDate, $toDate);

        $trips = $query
            ->select([
                'id',
                'tenant_id',
                'vehicle_id',
                'total_distance_km',
                'planned_mileage_km_per_liter',
                'planned_diesel_liters',
            ])
            ->with([
                'expenses' => fn ($q) => $q
                    ->select(['id', 'trip_id', 'expense_type', 'fuel_liters', 'amount'])
                    ->where('expense_type', TripExpenseType::Diesel->value),
                'vehicle:id,default_mileage_km_per_liter',
            ])
            ->get();

        $totalDistance = (float) $trips->sum('total_distance_km');
        $totalFuelLiters = 0.0;
        $totalFuelCost = 0.0;
        $totalExpectedFuel = 0.0;

        foreach ($trips as $trip) {
            $fuelExpenses = $trip->expenses;
            $totalFuelLiters += (float) $fuelExpenses->sum('fuel_liters');
            $totalFuelCost += (float) $fuelExpenses->sum('amount');

            $plannedMileage = (float) ($trip->planned_mileage_km_per_liter ?? 0);
            if ($plannedMileage <= 0) {
                $plannedMileage = (float) ($trip->vehicle?->default_mileage_km_per_liter ?? 0);
            }

            $distance = (float) ($trip->total_distance_km ?? 0);
            if ((float) ($trip->planned_diesel_liters ?? 0) > 0) {
                $totalExpectedFuel += (float) $trip->planned_diesel_liters;
            } elseif ($plannedMileage > 0 && $distance > 0) {
                $totalExpectedFuel += round($distance / $plannedMileage, 2);
            }
        }

        $avgMileage = $totalFuelLiters > 0 ? round($totalDistance / $totalFuelLiters, 2) : 0.0;
        $costPerKm = $totalDistance > 0 ? round($totalFuelCost / $totalDistance, 2) : 0.0;
        $varianceLiters = $totalExpectedFuel > 0 ? round($totalFuelLiters - $totalExpectedFuel, 2) : 0.0;
        $variancePct = $totalExpectedFuel > 0 ? round(($varianceLiters / $totalExpectedFuel) * 100, 2) : 0.0;

        return [
            'total_fuel_liters' => round($totalFuelLiters, 2),
            'total_fuel_cost' => round($totalFuelCost, 2),
            'total_distance_km' => round($totalDistance, 2),
            'average_mileage' => $avgMileage,
            'cost_per_km' => $costPerKm,
            'expected_fuel_liters' => round($totalExpectedFuel, 2),
            'fuel_variance_liters' => $varianceLiters,
            'fuel_variance_percentage' => $variancePct,
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function buildTyreInventoryStats(): array
    {
        return [
            'total_count' => Tyre::query()->count(),
            'in_stock_count' => Tyre::query()->where('status', TyreStatus::InStock->value)->count(),
            'installed_count' => Tyre::query()->where('status', TyreStatus::Installed->value)->count(),
            'scrapped_count' => Tyre::query()->where('status', TyreStatus::Scrapped->value)->count(),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private static function buildTyrePeriodStats(string $fromDate, string $toDate): array
    {
        $purchaseQuery = Tyre::query()
            ->whereNotNull('purchase_date')
            ->whereDate('purchase_date', '>=', $fromDate)
            ->whereDate('purchase_date', '<=', $toDate);

        $scrappedQuery = Tyre::query()
            ->where('status', TyreStatus::Scrapped->value)
            ->whereNotNull('scrapped_at')
            ->whereDate('scrapped_at', '>=', $fromDate)
            ->whereDate('scrapped_at', '<=', $toDate);

        return [
            'purchased_count' => (clone $purchaseQuery)->count(),
            'purchased_total_cost' => round((float) (clone $purchaseQuery)->sum('purchase_cost'), 2),
            'expenses_total' => round((float) TyreExpense::query()
                ->where('expense_type', '!=', TyreExpenseType::Purchase->value)
                ->whereDate('expense_date', '>=', $fromDate)
                ->whereDate('expense_date', '<=', $toDate)
                ->sum('amount'), 2),
            'period_scrapped_count' => (clone $scrappedQuery)->count(),
            'period_scrapped_recovery_amount' => round((float) (clone $scrappedQuery)->sum('scrap_sale_amount'), 2),
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function buildTyreAlertStats(): array
    {
        return app(TyreAlertService::class)->dashboardCounts();
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildFleetStatusStats(string $fromDate, string $toDate): array
    {
        $vehicles = Vehicle::query()
            ->with(['trips' => function ($query) {
                $query->whereIn('status', [
                    TripStatus::InProgress->value,
                    TripStatus::Completed->value,
                    TripStatus::Invoiced->value,
                ])->orderByDesc('start_at');
            }])
            ->get();

        $totalTrucks = $vehicles->count();
        $unavailableCount = 0;
        $onTripCount = 0;
        $idleTrucksList = [];

        $now = Carbon::now();

        foreach ($vehicles as $vehicle) {
            if (! $vehicle->is_active) {
                $unavailableCount++;
                continue;
            }

            // Check if truck is currently on trip
            $inProgressTrip = $vehicle->trips->first(fn ($t) => ($t->status?->value ?? (string) $t->status) === TripStatus::InProgress->value);

            if ($inProgressTrip !== null) {
                $onTripCount++;
                continue;
            }

            // Vehicle is active and not on trip -> Find last completed/invoiced trip
            $lastCompletedTrip = $vehicle->trips->first(fn ($t) => in_array($t->status?->value ?? (string) $t->status, [
                TripStatus::Completed->value,
                TripStatus::Invoiced->value,
            ], true));

            if ($lastCompletedTrip !== null) {
                $lastTripEnd = $lastCompletedTrip->end_at ?? $lastCompletedTrip->actual_arrival_at ?? $lastCompletedTrip->start_at;
                $idleSince = $lastTripEnd ? Carbon::parse($lastTripEnd) : Carbon::parse($vehicle->created_at);
                $lastTripNumber = $lastCompletedTrip->trip_number;
                $lastTripId = $lastCompletedTrip->id;
            } else {
                $idleSince = Carbon::parse($vehicle->created_at);
                $lastTripNumber = null;
                $lastTripId = null;
            }

            $idleDays = max(0, (int) $idleSince->copy()->startOfDay()->diffInDays($now->copy()->startOfDay()));

            $idleTrucksList[] = [
                'id' => $vehicle->id,
                'registration_number' => $vehicle->registration_number,
                'last_trip_id' => $lastTripId,
                'last_trip_number' => $lastTripNumber,
                'last_trip_end' => $lastCompletedTrip ? Carbon::parse($lastCompletedTrip->end_at ?? $lastCompletedTrip->start_at)->format('d M') : null,
                'last_trip_end_date' => $lastCompletedTrip ? Carbon::parse($lastCompletedTrip->end_at ?? $lastCompletedTrip->start_at)->format('Y-m-d') : null,
                'idle_since' => $idleSince->format('d M'),
                'idle_since_date' => $idleSince->format('Y-m-d'),
                'idle_days' => $idleDays,
                'is_active' => (bool) $vehicle->is_active,
            ];
        }

        // Retrieve due services to identify vehicles requiring or undergoing maintenance
        $maintenanceVehicleIds = [];
        try {
            $dueServices = app(VehicleMaintenanceService::class)->dueServicesForAlerts();
            $maintenanceVehicleIds = $dueServices->pluck('vehicle_id')->unique()->filter()->values()->all();
        } catch (\Throwable) {
            $maintenanceVehicleIds = [];
        }
        $maintenanceCount = count($maintenanceVehicleIds);

        // Sort idle trucks descending by idle_days (highest idle first)
        usort($idleTrucksList, fn ($a, $b) => $b['idle_days'] <=> $a['idle_days']);

        // Default: 1-3 days = normal turnaround (Available), 4+ days = attention/idle (Idle)
        $normalThresholdMax = 3;
        // Trucks due for maintenance are tracked under maintenance
        $idleCount = count(array_filter($idleTrucksList, fn ($t) => ! in_array($t['id'], $maintenanceVehicleIds, true) && $t['idle_days'] > $normalThresholdMax));
        $availableCount = count(array_filter($idleTrucksList, fn ($t) => ! in_array($t['id'], $maintenanceVehicleIds, true) && $t['idle_days'] <= $normalThresholdMax));

        $utilization = self::buildFleetUtilizationStats($vehicles, $fromDate, $toDate);

        return [
            'overview' => [
                'total_trucks' => $totalTrucks,
                'on_trip' => $onTripCount,
                'available' => $availableCount,
                'idle' => $idleCount,
                'maintenance' => $maintenanceCount,
                'unavailable' => $unavailableCount,
            ],
            'idle_trucks' => array_map(function ($item) use ($maintenanceVehicleIds) {
                $item['is_due_maintenance'] = in_array($item['id'], $maintenanceVehicleIds, true);

                return $item;
            }, $idleTrucksList),
            'utilization' => $utilization,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Vehicle>  $vehicles
     * @return array<string, mixed>
     */
    private static function buildFleetUtilizationStats($vehicles, string $fromDate, string $toDate): array
    {
        $start = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->endOfDay();
        $periodDays = max(1, (int) Carbon::parse($fromDate)->diffInDays(Carbon::parse($toDate)) + 1);

        $totalFleet = $vehicles->count();
        $totalFleetDays = $totalFleet * $periodDays;

        $inactiveVehiclesCount = $vehicles->where('is_active', false)->count();
        $unavailableDays = $inactiveVehiclesCount * $periodDays;
        $availableDays = max(0, $totalFleetDays - $unavailableDays);

        $vehicleIds = $vehicles->where('is_active', true)->pluck('id');

        $trips = Trip::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->whereIn('status', [
                TripStatus::InProgress->value,
                TripStatus::Completed->value,
                TripStatus::Invoiced->value,
            ])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('start_at', [$start, $end])
                    ->orWhereBetween('end_at', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->where('start_at', '<=', $start)
                            ->where(function ($sub) use ($end) {
                                $sub->where('end_at', '>=', $end)
                                    ->orWhereNull('end_at');
                            });
                    });
            })
            ->get(['id', 'vehicle_id', 'start_at', 'end_at', 'status']);

        $tripsByVehicle = $trips->groupBy('vehicle_id');
        $now = Carbon::now();
        $runningDays = 0;

        foreach ($vehicles as $vehicle) {
            if (! $vehicle->is_active) {
                continue;
            }

            $vTrips = $tripsByVehicle->get($vehicle->id);
            if (! $vTrips || $vTrips->isEmpty()) {
                continue;
            }

            $currentDay = $start->copy();
            while ($currentDay->lte($end)) {
                $dayStart = $currentDay->copy()->startOfDay();
                $dayEnd = $currentDay->copy()->endOfDay();

                $isRunning = $vTrips->contains(function ($trip) use ($dayStart, $dayEnd, $now) {
                    $tStart = Carbon::parse($trip->start_at);
                    $tEnd = $trip->end_at ? Carbon::parse($trip->end_at) : $now;

                    return $tStart->lte($dayEnd) && $tEnd->gte($dayStart);
                });

                if ($isRunning) {
                    $runningDays++;
                }

                $currentDay->addDay();
            }
        }

        $idleDays = max(0, $availableDays - $runningDays);
        $utilizationRate = $availableDays > 0 ? round(($runningDays / $availableDays) * 100, 1) : 0.0;

        return [
            'total_fleet' => $totalFleet,
            'period_days' => $periodDays,
            'available_days' => $availableDays,
            'running_days' => $runningDays,
            'idle_days' => $idleDays,
            'unavailable_days' => $unavailableDays,
            'utilization_rate' => $utilizationRate,
        ];
    }
}
