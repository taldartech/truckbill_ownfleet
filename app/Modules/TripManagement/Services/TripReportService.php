<?php

namespace App\Modules\TripManagement\Services;

use App\Modules\TripManagement\Repositories\TripRepository;
use App\Trip;
use App\TripExpense;
use App\TripRouteLeg;
use Illuminate\Database\Eloquent\Collection;

class TripReportService
{
    private const REPORT_ROW_LIMIT = 500;

    public function __construct(private readonly TripRepository $tripRepository) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filteredTripCount(array $filters = []): int
    {
        return $this->tripRepository->filteredQuery($filters)->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function profitability(array $filters = []): array
    {
        $rows = $this->tripRepository->filteredQuery($filters)
            ->with(['vehicle', 'driver'])
            ->latest('start_at')
            ->limit(self::REPORT_ROW_LIMIT)
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
                'loaded_distance_km' => (float) $trip->loaded_distance_km,
                'empty_distance_km' => (float) $trip->empty_distance_km,
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
        $rows = $this->tripRepository->filteredQuery($filters)
            ->with('vehicle')
            ->limit(self::REPORT_ROW_LIMIT)
            ->get()
            ->groupBy('vehicle_id')
            ->map(function (Collection $trips): array {
                $vehicle = $trips->first()?->vehicle;

                return [
                    'registration_number' => $vehicle?->registration_number,
                    'trip_count' => $trips->count(),
                    'total_distance_km' => (float) $trips->sum('total_distance_km'),
                    'loaded_distance_km' => (float) $trips->sum('loaded_distance_km'),
                    'empty_distance_km' => (float) $trips->sum('empty_distance_km'),
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
        $rows = $this->tripRepository->filteredQuery($filters)
            ->with('driver')
            ->limit(self::REPORT_ROW_LIMIT)
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
        $rows = $this->tripRepository->filteredQuery($filters)
            ->with(['vehicle', 'driver'])
            ->limit(self::REPORT_ROW_LIMIT)
            ->get()
            ->map(fn (Trip $trip): array => [
                'trip_number' => $trip->trip_number,
                'vehicle' => $trip->vehicle?->registration_number,
                'driver' => $trip->driver?->name,
                'empty_distance_km' => (float) $trip->empty_distance_km,
                'loaded_distance_km' => (float) $trip->loaded_distance_km,
                'empty_percentage' => $trip->total_distance_km > 0
                    ? round(((float) $trip->empty_distance_km / (float) $trip->total_distance_km) * 100, 2)
                    : 0,
            ])
            ->all();

        return $this->withSerialNumbers($rows);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function expenseSummary(array $filters = []): array
    {
        $tripIds = $this->tripRepository->filteredQuery($filters)
            ->limit(self::REPORT_ROW_LIMIT)
            ->pluck('id');

        $rows = TripExpense::query()
            ->whereIn('trip_id', $tripIds)
            ->limit(5000)
            ->get()
            ->groupBy(fn (TripExpense $expense) => $expense->expense_type->value ?? (string) $expense->expense_type)
            ->map(fn (Collection $items, string $type): array => [
                'expense_type' => $type,
                'trip_count' => $items->pluck('trip_id')->unique()->count(),
                'entry_count' => $items->count(),
                'total_amount' => (float) $items->sum('amount'),
            ])
            ->values()
            ->all();

        return $this->withSerialNumbers($rows);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function routeProfitability(array $filters = []): array
    {
        $tripIds = $this->tripRepository->filteredQuery($filters)
            ->limit(self::REPORT_ROW_LIMIT)
            ->pluck('id');

        $rows = TripRouteLeg::query()
            ->whereIn('trip_id', $tripIds)
            ->with('expenses')
            ->limit(5000)
            ->get()
            ->groupBy(fn (TripRouteLeg $leg): string => $leg->from_location.' → '.$leg->to_location)
            ->map(function (Collection $legs, string $route): array {
                $freight = (float) $legs->sum('freight_amount');
                $legExpenses = (float) $legs->sum(fn (TripRouteLeg $leg): float => (float) $leg->expenses->sum('amount'));

                return [
                    'route' => $route,
                    'trip_count' => $legs->pluck('trip_id')->unique()->count(),
                    'total_distance_km' => (float) $legs->sum('distance_km'),
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
