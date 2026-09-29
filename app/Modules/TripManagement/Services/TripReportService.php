<?php

namespace App\Modules\TripManagement\Services;

use App\Enums\LoadCategory;
use App\Enums\TripExpenseType;
use App\Modules\TripManagement\Repositories\TripRepository;
use App\Modules\TripManagement\Support\LoadClassifier;
use App\MonthlyExpense;
use App\Trip;
use App\TripExpense;
use App\TripRouteLeg;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TripReportService
{
    public function __construct(private readonly TripRepository $tripRepository) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filteredTripCount(array $filters = []): int
    {
        return $this->tripRepository->reportFilteredQuery($filters)->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     trip_count: int,
     *     total_freight_amount: float,
     *     total_expense_amount: float,
     *     monthly_expenses: float,
     *     profit_amount: float
     * }
     */
    public function financialSummary(array $filters = []): array
    {
        $trips = $this->tripRepository->reportFilteredQuery($filters)->get();

        $totalFreight = (float) $trips->sum('total_freight_amount');
        $totalExpense = (float) $trips->sum('total_expense_amount');
        $tripProfit = (float) $trips->sum(fn (Trip $trip) => $trip->profitAmount());
        $monthlyExpenses = $this->monthlyExpensesForFilters($filters);

        return [
            'trip_count' => $trips->count(),
            'total_freight_amount' => round($totalFreight, 2),
            'total_expense_amount' => round($totalExpense, 2),
            'monthly_expenses' => $monthlyExpenses,
            'profit_amount' => round($tripProfit - $monthlyExpenses, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function monthlyExpensesForFilters(array $filters): float
    {
        $fromDate = ! empty($filters['from_date'])
            ? (string) $filters['from_date']
            : Carbon::today()->startOfMonth()->toDateString();
        $toDate = ! empty($filters['to_date'])
            ? (string) $filters['to_date']
            : Carbon::today()->toDateString();

        return MonthlyExpense::sumForMonthRange($fromDate, $toDate);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function profitability(array $filters = []): array
    {
        $rows = $this->tripRepository->reportFilteredQuery($filters)
            ->with(['vehicle', 'driver'])
            ->latest('start_at')
            ->get()
            ->map(fn (Trip $trip): array => [
                'trip_number' => $trip->trip_number,
                'vehicle' => $trip->vehicle?->registration_number,
                'driver' => $trip->driver?->name,
                'customer_name' => $trip->customer_name,
                'start_at' => $trip->start_at?->toDateString(),
                'total_freight_amount' => (float) $trip->total_freight_amount,
                'total_expense_amount' => (float) $trip->total_expense_amount,
                'profit_amount' => $trip->profitAmount(),
                'total_distance_km' => (float) $trip->total_distance_km,
            ])
            ->all();

        return $this->withSerialNumbers($rows);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function vehicleWise(array $filters = []): array
    {
        $rows = $this->tripRepository->reportFilteredQuery($filters)
            ->with('vehicle')
            ->get()
            ->groupBy('vehicle_id')
            ->map(function (Collection $trips): array {
                $vehicle = $trips->first()?->vehicle;

                return [
                    'registration_number' => $vehicle?->registration_number,
                    'trip_count' => $trips->count(),
                    'total_distance_km' => (float) $trips->sum('total_distance_km'),
                    'total_freight_amount' => (float) $trips->sum('total_freight_amount'),
                    'total_expense_amount' => (float) $trips->sum('total_expense_amount'),
                    'profit_amount' => (float) $trips->sum(fn (Trip $trip) => $trip->profitAmount()),
                ];
            })
            ->values()
            ->all();

        return $this->withSerialNumbers($rows);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function driverWise(array $filters = []): array
    {
        $rows = $this->tripRepository->reportFilteredQuery($filters)
            ->with('driver')
            ->get()
            ->groupBy('driver_id')
            ->map(function (Collection $trips): array {
                $driver = $trips->first()?->driver;

                return [
                    'driver_name' => $driver?->name,
                    'trip_count' => $trips->count(),
                    'total_distance_km' => (float) $trips->sum('total_distance_km'),
                    'total_freight_amount' => (float) $trips->sum('total_freight_amount'),
                    'total_expense_amount' => (float) $trips->sum('total_expense_amount'),
                    'profit_amount' => (float) $trips->sum(fn (Trip $trip) => $trip->profitAmount()),
                ];
            })
            ->values()
            ->all();

        return $this->withSerialNumbers($rows);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function emptyKm(array $filters = []): array
    {
        $rows = $this->tripRepository->reportFilteredQuery($filters)
            ->with(['vehicle', 'driver', 'routeLegs'])
            ->get()
            ->map(function (Trip $trip): array {
                $distances = $this->resolveLoadDistances($trip);

                return [
                    'trip_number' => $trip->trip_number,
                    'vehicle' => $trip->vehicle?->registration_number,
                    'driver' => $trip->driver?->name,
                    'empty_distance_km' => $distances['empty_distance_km'],
                    'loaded_distance_km' => $distances['loaded_distance_km'],
                    'empty_percentage' => $trip->total_distance_km > 0
                        ? round(($distances['empty_distance_km'] / (float) $trip->total_distance_km) * 100, 2)
                        : 0,
                ];
            })
            ->all();

        return $this->withSerialNumbers($rows);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function expenseSummary(array $filters = []): array
    {
        $tripFilters = $filters;
        $driverId = $tripFilters['driver_id'] ?? null;
        unset($tripFilters['from_date'], $tripFilters['to_date'], $tripFilters['driver_id']);

        $tripQuery = $this->tripRepository->reportFilteredQuery($tripFilters);

        if (! empty($driverId)) {
            $tripQuery->where(function ($query) use ($driverId): void {
                $query->where('substitute_driver_id', $driverId)
                    ->orWhere(function ($sub) use ($driverId): void {
                        $sub->whereNull('substitute_driver_id')
                            ->where('driver_id', $driverId);
                    });
            });
        }

        $tripIds = $tripQuery->pluck('id');

        $query = TripExpense::query()
            ->whereIn('trip_id', $tripIds)
            ->with(['trip.vehicle', 'trip.driver', 'trip.substituteDriver']);

        if (! empty($filters['expense_type'])) {
            $query->where('expense_type', strtoupper((string) $filters['expense_type']));
        }

        if (! empty($filters['from_date'])) {
            $query->where('expense_date', '>=', (string) $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->where('expense_date', '<=', (string) $filters['to_date']);
        }

        $expenses = $query->latest('expense_date')->get();

        $rows = $expenses->map(function (TripExpense $expense): array {
            $trip = $expense->trip;

            return [
                'expense_id' => $expense->id,
                'trip_id' => $trip?->id,
                'trip_number' => $trip?->trip_number,
                'vehicle' => $trip?->vehicle?->registration_number,
                'driver' => $trip?->substituteDriver?->name ?? $trip?->driver?->name,
                'expense_type' => $expense->expense_type instanceof TripExpenseType ? $expense->expense_type->value : (string) $expense->expense_type,
                'expense_type_label' => $expense->expense_type instanceof TripExpenseType ? $expense->expense_type->label() : ($expense->expense_type ?? 'Expense'),
                'other_type' => $expense->other_type,
                'expense_date' => $expense->expense_date?->toDateString(),
                'amount' => (float) $expense->amount,
                'fuel_liters' => $expense->fuel_liters !== null ? (float) $expense->fuel_liters : null,
                'fuel_rate' => $expense->fuel_rate !== null ? (float) $expense->fuel_rate : null,
                'fuel_odometer_km' => $expense->fuel_odometer_km !== null ? (float) $expense->fuel_odometer_km : null,
                'notes' => $expense->notes,
            ];
        })->values()->all();

        usort($rows, function (array $a, array $b): int {
            $cmp = strnatcasecmp((string) ($a['trip_number'] ?? ''), (string) ($b['trip_number'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($b['expense_date'] ?? ''), (string) ($a['expense_date'] ?? ''));
        });

        return $this->withSerialNumbers($rows);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function routeProfitability(array $filters = []): array
    {
        $tripIds = $this->tripRepository->reportFilteredQuery($filters)
            ->pluck('id');

        $legs = TripRouteLeg::query()
            ->whereIn('trip_id', $tripIds)
            ->with(['expenses', 'trip:id,total_distance_km'])
            ->get();

        $legsByTrip = $legs->groupBy('trip_id');

        $rows = $legs
            ->map(function (TripRouteLeg $leg) use ($legsByTrip): array {
                $tripLegs = $legsByTrip->get($leg->trip_id, collect());

                return [
                    'route' => ($leg->from_city ?? '—').' → '.($leg->to_city ?? '—'),
                    'trip_id' => $leg->trip_id,
                    'distance_km' => $this->resolveLegDistanceKm($leg, $tripLegs),
                    'freight_amount' => (float) $leg->freight_amount,
                    'expense_amount' => (float) $leg->expenses->sum('amount'),
                ];
            })
            ->groupBy('route')
            ->map(function (Collection $items, string $route): array {
                $freight = (float) $items->sum('freight_amount');
                $legExpenses = (float) $items->sum('expense_amount');

                return [
                    'route' => $route,
                    'trip_count' => $items->pluck('trip_id')->unique()->count(),
                    'total_distance_km' => (float) $items->sum('distance_km'),
                    'total_freight_amount' => $freight,
                    'total_expense_amount' => $legExpenses,
                    'profit_amount' => $freight - $legExpenses,
                ];
            })
            ->values()
            ->all();

        return $this->withSerialNumbers($rows);
    }

    /**
     * @param  Collection<int, TripRouteLeg>  $tripLegs
     */
    private function resolveLegDistanceKm(TripRouteLeg $leg, Collection $tripLegs): float
    {
        if ((float) $leg->distance_km > 0) {
            return (float) $leg->distance_km;
        }

        $tripTotal = (float) ($leg->trip?->total_distance_km ?? 0);

        if ($tripTotal <= 0 || $tripLegs->isEmpty()) {
            return 0.0;
        }

        $legsWithStoredDistance = $tripLegs->filter(
            fn (TripRouteLeg $routeLeg): bool => (float) $routeLeg->distance_km > 0,
        );
        $storedTotal = (float) $legsWithStoredDistance->sum('distance_km');

        if ($storedTotal > 0) {
            $remainder = max(0, $tripTotal - $storedTotal);
            $legsWithoutDistance = $tripLegs->filter(
                fn (TripRouteLeg $routeLeg): bool => (float) $routeLeg->distance_km <= 0,
            );

            if (! $legsWithoutDistance->contains('id', $leg->id)) {
                return 0.0;
            }

            return $legsWithoutDistance->count() > 0
                ? round($remainder / $legsWithoutDistance->count(), 2)
                : 0.0;
        }

        return round($tripTotal / $tripLegs->count(), 2);
    }

    /**
     * @return array{empty_distance_km: float, loaded_distance_km: float}
     */
    private function resolveLoadDistances(Trip $trip): array
    {
        $totalDistance = (float) ($trip->total_distance_km ?? 0);
        $legs = $trip->routeLegs;

        if ($legs->isEmpty() || $totalDistance <= 0) {
            return [
                'empty_distance_km' => 0.0,
                'loaded_distance_km' => 0.0,
            ];
        }

        $maxLoadTon = (float) ($trip->max_load_ton ?? 0);
        $perLegKm = $totalDistance / $legs->count();
        $emptyKm = 0.0;
        $loadedKm = 0.0;

        foreach ($legs as $leg) {
            $classification = LoadClassifier::classify((float) ($leg->weight_ton ?? 0), $maxLoadTon);

            if ($classification->category === LoadCategory::Empty) {
                $emptyKm += $perLegKm;
            } else {
                $loadedKm += $perLegKm;
            }
        }

        return [
            'empty_distance_km' => round($emptyKm, 2),
            'loaded_distance_km' => round($loadedKm, 2),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withSerialNumbers(array $rows): array
    {
        $numbered = [];

        foreach (array_values($rows) as $index => $row) {
            $numbered[] = ['s_no' => $index + 1] + $row;
        }

        return $numbered;
    }
}
