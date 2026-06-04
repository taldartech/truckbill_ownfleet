<?php

namespace App\Modules\TripManagement\Services;

use App\Enums\FreightPaymentStatus;
use App\Enums\RouteType;
use App\Modules\TripManagement\Repositories\TripRepository;
use App\Trip;
use App\TripExpense;
use App\TripRouteLeg;
use Illuminate\Database\Eloquent\Collection;

class TripReportService
{
    public function __construct(private readonly TripRepository $tripRepository) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function profitability(array $filters = []): array
    {
        $rows = $this->tripRepository->filteredQuery($filters)
            ->with(['vehicle', 'driver'])
            ->latest('start_at')
            ->limit(500)
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
        $tripIds = $this->tripRepository->filteredQuery($filters)->pluck('id');

        $rows = TripExpense::query()
            ->whereIn('trip_id', $tripIds)
            ->get()
            ->groupBy(fn (TripExpense $expense) => $expense->expense_type->value ?? (string) $expense->expense_type)
            ->map(fn (Collection $items, string $type): array => [
                'expense_type' => $type,
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
        $tripIds = $this->tripRepository->filteredQuery($filters)->pluck('id');

        $rows = TripRouteLeg::query()
            ->whereIn('trip_id', $tripIds)
            ->with(['trip.vehicle', 'expenses'])
            ->orderBy('sequence_no')
            ->get()
            ->map(function (TripRouteLeg $leg): array {
                $legExpenses = (float) $leg->expenses->sum('amount');
                $freight = (float) $leg->freight_amount;

                return [
                    'trip_number' => $leg->trip?->trip_number,
                    'vehicle' => $leg->trip?->vehicle?->registration_number,
                    'sequence_no' => $leg->sequence_no,
                    'route' => $leg->from_location.' → '.$leg->to_location,
                    'route_type' => $leg->route_type->value ?? (string) $leg->route_type,
                    'distance_km' => (float) $leg->distance_km,
                    'freight_amount' => $freight,
                    'expense_amount' => $legExpenses,
                    'profit_amount' => $freight - $legExpenses,
                ];
            })
            ->all();

        return $this->withSerialNumbers($rows);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function pendingFreightCollection(array $filters = []): array
    {
        $tripIds = $this->tripRepository->filteredQuery($filters)->pluck('id');

        $rows = TripRouteLeg::query()
            ->whereIn('trip_id', $tripIds)
            ->where('route_type', RouteType::Loaded->value)
            ->whereIn('payment_status', [
                FreightPaymentStatus::Pending->value,
                FreightPaymentStatus::Partial->value,
            ])
            ->with(['trip.vehicle', 'trip.driver'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (TripRouteLeg $leg): array => [
                'trip_number' => $leg->trip?->trip_number,
                'vehicle' => $leg->trip?->vehicle?->registration_number,
                'driver' => $leg->trip?->driver?->name,
                'customer_name' => $leg->trip?->customer_name,
                'route' => $leg->from_location.' → '.$leg->to_location,
                'freight_amount' => (float) $leg->freight_amount,
                'advance_received' => (float) $leg->advance_received,
                'balance_amount' => (float) $leg->balance_amount,
                'payment_status' => $leg->payment_status->value ?? (string) $leg->payment_status,
                'billing_status' => $leg->billing_status->value ?? (string) $leg->billing_status,
            ])
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
