<?php

namespace App\Support;

use App\Enums\TripStatus;
use App\Trip;
use App\Vehicle;
use App\VehicleMaintenance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Group-level fleet position, utilization and idle trucks across a Super Admin's companies.
 *
 * Every vehicle lands in exactly one status: Unavailable (inactive) → On Trip → Maintenance (service due)
 * → Idle (no trip for more than AVAILABLE_MAX_IDLE_DAYS) → Available.
 */
class SuperAdminFleetStats
{
    private const AVAILABLE_MAX_IDLE_DAYS = 3;

    public const IDLE_WARNING_DAYS = 7;

    public const IDLE_CRITICAL_DAYS = 15;

    private const IDLE_VEHICLES_LIMIT = 200;

    /**
     * @param  list<string>  $tenantIds
     * @param  list<string>  $trendMonths  Month keys in `Y-m` format.
     * @param  Collection<string, string>  $companyNames
     * @param  Collection<int, string>  $branchNames
     * @return array<string, mixed>
     */
    public static function build(
        array $tenantIds,
        Carbon $from,
        Carbon $to,
        array $trendMonths,
        Collection $companyNames,
        Collection $branchNames,
    ): array {
        $today = Carbon::today();

        $vehicles = SuperAdminPortfolioScope::apply(Vehicle::query(), $tenantIds)
            ->get(['vehicles.id', 'vehicles.tenant_id', 'vehicles.branch_id', 'vehicles.registration_number', 'vehicles.is_active', 'vehicles.created_at']);

        $onTripVehicleIds = SuperAdminPortfolioScope::apply(Trip::query(), $tenantIds)
            ->where('trips.status', TripStatus::InProgress->value)
            ->distinct()
            ->pluck('trips.vehicle_id')
            ->flip();

        $lastTripEnds = SuperAdminPortfolioScope::apply(Trip::query(), $tenantIds)
            ->whereIn('trips.status', [TripStatus::Completed->value, TripStatus::Invoiced->value])
            ->groupBy('trips.vehicle_id')
            ->selectRaw('trips.vehicle_id, MAX(COALESCE(trips.end_at, trips.actual_arrival_at, trips.start_at)) as last_trip_end')
            ->toBase()
            ->pluck('last_trip_end', 'vehicle_id');

        $dueServiceTenantByVehicle = self::dueServiceTenantByVehicle($tenantIds);

        $overview = [
            'total_trucks' => $vehicles->count(),
            'on_trip' => 0,
            'available' => 0,
            'idle' => 0,
            'maintenance' => 0,
            'unavailable' => 0,
        ];
        $byCompany = [];
        $idleVehicles = [];
        $idleWarningByCompany = [];

        foreach ($vehicles as $vehicle) {
            $tenantId = (string) $vehicle->tenant_id;
            $byCompany[$tenantId] ??= ['vehicles_count' => 0, 'active_vehicles_count' => 0, 'on_trip' => 0];
            $byCompany[$tenantId]['vehicles_count']++;

            if (! $vehicle->is_active) {
                $overview['unavailable']++;

                continue;
            }

            $byCompany[$tenantId]['active_vehicles_count']++;

            if ($onTripVehicleIds->has($vehicle->id)) {
                $overview['on_trip']++;
                $byCompany[$tenantId]['on_trip']++;

                continue;
            }

            $lastTripEnd = $lastTripEnds->get($vehicle->id);
            $idleSince = $lastTripEnd !== null ? Carbon::parse($lastTripEnd) : ($vehicle->created_at ?? $today);
            $idleDays = max(0, (int) $idleSince->copy()->startOfDay()->diffInDays($today));
            $isDueForService = $dueServiceTenantByVehicle->has($vehicle->id);

            if ($isDueForService) {
                $overview['maintenance']++;
            } elseif ($idleDays > self::AVAILABLE_MAX_IDLE_DAYS) {
                $overview['idle']++;
            } else {
                $overview['available']++;
            }

            if ($idleDays >= self::IDLE_WARNING_DAYS) {
                $idleWarningByCompany[$tenantId] = ($idleWarningByCompany[$tenantId] ?? 0) + 1;
                $idleVehicles[] = [
                    'id' => (string) $vehicle->id,
                    'registration_number' => $vehicle->registration_number,
                    'company_id' => $tenantId,
                    'company_name' => $companyNames->get($tenantId),
                    'branch_id' => $vehicle->branch_id !== null ? (int) $vehicle->branch_id : null,
                    'branch_name' => $branchNames->get($vehicle->branch_id),
                    'idle_days' => $idleDays,
                    'last_trip_end_date' => $lastTripEnd !== null ? Carbon::parse($lastTripEnd)->toDateString() : null,
                    'is_due_for_service' => $isDueForService,
                ];
            }
        }

        usort($idleVehicles, static fn (array $a, array $b): int => $b['idle_days'] <=> $a['idle_days']);

        $activeVehicles = $vehicles->where('is_active', true);
        $utilization = self::utilization($tenantIds, $activeVehicles, $from, $to, $trendMonths);

        foreach ($byCompany as $tenantId => $row) {
            $byCompany[$tenantId]['utilization_rate'] = $utilization['by_company'][$tenantId] ?? 0.0;
        }

        $onTripByCompany = collect($byCompany)
            ->filter(static fn (array $row): bool => $row['on_trip'] > 0)
            ->map(static fn (array $row, string $tenantId): array => [
                'company_id' => $tenantId,
                'company_name' => $companyNames->get($tenantId),
                'count' => $row['on_trip'],
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        return [
            'overview' => $overview,
            'utilization' => $utilization['period'],
            'idle' => [
                'warning_days' => self::IDLE_WARNING_DAYS,
                'critical_days' => self::IDLE_CRITICAL_DAYS,
                'over_warning_days' => count($idleVehicles),
                'over_critical_days' => count(array_filter(
                    $idleVehicles,
                    static fn (array $vehicle): bool => $vehicle['idle_days'] >= self::IDLE_CRITICAL_DAYS,
                )),
                'vehicles' => array_slice($idleVehicles, 0, self::IDLE_VEHICLES_LIMIT),
            ],
            'on_trip_by_company' => $onTripByCompany,
            'by_company' => $byCompany,
            'monthly_utilization' => $utilization['monthly'],
            'idle_warning_by_company' => $idleWarningByCompany,
            'due_services_by_company' => $dueServiceTenantByVehicle->countBy()->all(),
        ];
    }

    /**
     * Mirrors VehicleMaintenanceRepository::dueServicesForAlerts() for many companies at once:
     * a vehicle is due when its latest service record is past the due date or the odometer passed the due km.
     *
     * @param  list<string>  $tenantIds
     * @return Collection<string, string> vehicle id => tenant id
     */
    private static function dueServiceTenantByVehicle(array $tenantIds): Collection
    {
        $today = Carbon::today()->toDateString();

        return SuperAdminPortfolioScope::apply(VehicleMaintenance::query(), $tenantIds)
            ->join('vehicles', 'vehicles.id', '=', 'vehicle_maintenances.vehicle_id')
            ->whereNull('vehicles.deleted_at')
            ->whereRaw('vehicle_maintenances.id = (
                SELECT vm2.id
                FROM vehicle_maintenances AS vm2
                WHERE vm2.vehicle_id = vehicle_maintenances.vehicle_id
                  AND vm2.tenant_id = vehicle_maintenances.tenant_id
                  AND vm2.deleted_at IS NULL
                ORDER BY vm2.service_date DESC, vm2.created_at DESC
                LIMIT 1
            )')
            ->where(function (Builder $query) use ($today): void {
                $query
                    ->where(function (Builder $dueQuery) use ($today): void {
                        $dueQuery
                            ->whereNotNull('vehicle_maintenances.next_service_due_date')
                            ->whereDate('vehicle_maintenances.next_service_due_date', '<', $today);
                    })
                    ->orWhere(function (Builder $dueQuery): void {
                        $dueQuery
                            ->whereNotNull('vehicle_maintenances.next_service_due_km')
                            ->whereColumn('vehicles.odometer_km', '>=', 'vehicle_maintenances.next_service_due_km');
                    });
            })
            ->toBase()
            ->pluck('vehicle_maintenances.tenant_id', 'vehicle_maintenances.vehicle_id')
            ->map(static fn ($tenantId): string => (string) $tenantId);
    }

    /**
     * Utilization = running truck-days ÷ available truck-days, where a truck-day is "running"
     * when any in-progress/completed/invoiced trip covers that date.
     *
     * @param  list<string>  $tenantIds
     * @param  Collection<int, Vehicle>  $activeVehicles
     * @param  list<string>  $trendMonths
     * @return array{period: array<string, float|int>, by_company: array<string, float>, monthly: array<string, float>}
     */
    private static function utilization(
        array $tenantIds,
        Collection $activeVehicles,
        Carbon $from,
        Carbon $to,
        array $trendMonths,
    ): array {
        $today = Carbon::today();
        $trendStart = Carbon::createFromFormat('Y-m-d', ($trendMonths[0] ?? $from->format('Y-m')).'-01')->startOfDay();
        $trendEnd = Carbon::createFromFormat('Y-m-d', (end($trendMonths) ?: $to->format('Y-m')).'-01')->endOfMonth()->startOfDay();
        $windowStart = $from->lt($trendStart) ? $from->copy() : $trendStart;
        $windowEnd = $to->gt($trendEnd) ? $to->copy() : $trendEnd;
        $windowEnd = $windowEnd->gt($today) ? $today->copy() : $windowEnd;

        $runningDates = self::runningDatesByVehicle($tenantIds, $windowStart, $windowEnd);
        $fromKey = $from->toDateString();
        $toKey = $to->toDateString();
        $periodDays = (int) $from->diffInDays($to) + 1;

        $periodRunning = 0;
        $companyRunning = [];
        $companyAvailable = [];
        $monthRunning = [];

        foreach ($activeVehicles as $vehicle) {
            $tenantId = (string) $vehicle->tenant_id;
            $companyAvailable[$tenantId] = ($companyAvailable[$tenantId] ?? 0) + $periodDays;
            $companyRunning[$tenantId] ??= 0;

            foreach (array_keys($runningDates[$vehicle->id] ?? []) as $date) {
                if ($date >= $fromKey && $date <= $toKey) {
                    $periodRunning++;
                    $companyRunning[$tenantId]++;
                }

                $monthKey = substr($date, 0, 7);
                $monthRunning[$monthKey][$vehicle->id] = ($monthRunning[$monthKey][$vehicle->id] ?? 0) + 1;
            }
        }

        $periodAvailable = $activeVehicles->count() * $periodDays;

        $monthly = [];

        foreach ($trendMonths as $monthKey) {
            $monthStart = Carbon::createFromFormat('Y-m-d', "{$monthKey}-01")->startOfDay();

            if ($monthStart->gt($today)) {
                $monthly[$monthKey] = 0.0;

                continue;
            }

            $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
            $monthEnd = $monthEnd->gt($today) ? $today->copy() : $monthEnd;
            $days = (int) $monthStart->diffInDays($monthEnd) + 1;

            $eligible = $activeVehicles->filter(
                static fn (Vehicle $vehicle): bool => $vehicle->created_at === null || $vehicle->created_at->lte($monthEnd->copy()->endOfDay()),
            );
            $running = $eligible->sum(static fn (Vehicle $vehicle): int => $monthRunning[$monthKey][$vehicle->id] ?? 0);

            $monthly[$monthKey] = self::rate($running, $eligible->count() * $days);
        }

        $byCompany = [];

        foreach ($companyAvailable as $tenantId => $available) {
            $byCompany[$tenantId] = self::rate($companyRunning[$tenantId] ?? 0, $available);
        }

        return [
            'period' => [
                'rate' => self::rate($periodRunning, $periodAvailable),
                'running_days' => $periodRunning,
                'available_days' => $periodAvailable,
                'period_days' => $periodDays,
            ],
            'by_company' => $byCompany,
            'monthly' => $monthly,
        ];
    }

    /**
     * @param  list<string>  $tenantIds
     * @return array<string, array<string, true>> vehicle id => set of `Y-m-d` dates on trip
     */
    private static function runningDatesByVehicle(array $tenantIds, Carbon $windowStart, Carbon $windowEnd): array
    {
        $trips = SuperAdminPortfolioScope::apply(Trip::query(), $tenantIds)
            ->whereIn('trips.status', [
                TripStatus::InProgress->value,
                TripStatus::Completed->value,
                TripStatus::Invoiced->value,
            ])
            ->whereNotNull('trips.start_at')
            ->where('trips.start_at', '<=', $windowEnd->copy()->endOfDay())
            ->where(function (Builder $query) use ($windowStart): void {
                $query->whereNull('trips.end_at')
                    ->orWhere('trips.end_at', '>=', $windowStart->copy()->startOfDay());
            })
            ->toBase()
            ->get(['trips.vehicle_id', 'trips.start_at', 'trips.end_at']);

        $now = Carbon::now();
        $dates = [];

        foreach ($trips as $trip) {
            $start = Carbon::parse($trip->start_at)->startOfDay();
            $end = Carbon::parse($trip->end_at ?? $now)->startOfDay();
            $start = $start->lt($windowStart) ? $windowStart->copy() : $start;
            $end = $end->gt($windowEnd) ? $windowEnd->copy() : $end;

            for ($day = $start; $day->lte($end); $day->addDay()) {
                $dates[(string) $trip->vehicle_id][$day->toDateString()] = true;
            }
        }

        return $dates;
    }

    private static function rate(int|float $running, int|float $available): float
    {
        return $available > 0 ? round(($running / $available) * 100, 1) : 0.0;
    }
}
