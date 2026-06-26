<?php

namespace App\Modules\TripManagement\Services;

use App\Broker;
use App\Customer;
use App\Enums\FreightPaymentStatus;
use App\Enums\RouteType;
use App\Enums\TripExpenseType;
use App\Enums\TripStatus;
use App\Modules\TripManagement\Repositories\TripRepository;
use App\Station;
use App\Support\BranchContext;
use App\Support\CurrentTenant;
use App\Support\RouteLegLocationFormatter;
use App\Support\TripNumberGenerator;
use App\Trip;
use App\TripExpense;
use App\TripRouteLeg;
use App\TripStatusHistory;
use App\Vehicle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TripService
{
    public function __construct(
        private readonly TripRepository $tripRepository,
        private readonly TripValidationService $tripValidation,
        private readonly TripNumberGenerator $tripNumberGenerator,
        private readonly VehicleTripEligibilityService $vehicleTripEligibility,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->tripRepository->paginate($perPage, $filters);
    }

    public function previewNextTripNumber(): string
    {
        return $this->tripNumberGenerator->preview(
            CurrentTenant::id(),
            BranchContext::branchId(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $userId = null): Trip
    {
        return DB::transaction(function () use ($data, $userId): Trip {
            $routeLegs = $data['route_legs'] ?? [];
            $expenses = $data['expenses'] ?? [];
            unset($data['route_legs'], $data['expenses'], $data['trip_number']);

            $vehicleId = (string) $data['vehicle_id'];
            $vehicle = Vehicle::query()
                ->with(['currentPrimaryAssignment.driver'])
                ->findOrFail($vehicleId);

            $tripStartAt = $data['start_at'] ?? null;
            $tripStartDate = $tripStartAt !== null
                ? \Illuminate\Support\Carbon::parse($tripStartAt)->toDateString()
                : null;
            $tripEndDate = isset($data['end_at']) && $data['end_at'] !== null
                ? \Illuminate\Support\Carbon::parse($data['end_at'])->toDateString()
                : null;

            $this->vehicleTripEligibility->assertEligibleForTripCreation($vehicle, $tripStartDate, $tripEndDate);

            $resolvedDriverId = $this->tripValidation->resolvePrimaryDriverIdForVehicle($vehicleId, $tripStartAt);

            if ($resolvedDriverId === null) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'start_at' => ['No primary driver is assigned to this truck on the trip start date.'],
                    'vehicle_id' => ['Choose a different truck or adjust the trip start date.'],
                ]);
            }

            if (isset($data['driver_id']) && (string) $data['driver_id'] !== $resolvedDriverId) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'driver_id' => ['Driver must match the primary driver assigned to this truck.'],
                ]);
            }

            $driverId = $resolvedDriverId;

            $this->tripValidation->assertDriverIsActive($driverId);
            $this->tripValidation->assertDriverAssignedToVehicle($vehicleId, $driverId, $data['start_at'] ?? null);
            $this->tripValidation->assertRouteLegsAreValid($routeLegs, isset($data['start_station_id']) ? (int) $data['start_station_id'] : null);
            $this->tripValidation->assertPositiveFreightTotal($routeLegs);
            $this->tripValidation->assertExpensesAreValid(
                $expenses,
                $data['start_at'] ?? null,
                $data['end_at'] ?? null,
            );
            $this->tripValidation->assertNoDuplicateTripWindow(
                $vehicleId,
                $data['start_at'] ?? null,
                $data['end_at'] ?? null,
            );
            $this->tripValidation->assertNoDuplicateDriverTripWindow(
                $driverId,
                $data['start_at'] ?? null,
                $data['end_at'] ?? null,
            );

            if (array_key_exists('substitute_driver_id', $data)) {
                $this->tripValidation->assertSubstituteDriverIsValid($data['substitute_driver_id'] ?? null, $driverId);
                $data['substitute_driver_id'] = $data['substitute_driver_id'] ?: null;
            }

            $tenantId = CurrentTenant::id() ?? $data['tenant_id'] ?? null;
            $branchId = BranchContext::branchId() ?? (isset($data['branch_id']) ? (int) $data['branch_id'] : null);
            $tripNumber = $this->tripNumberGenerator->generate($tenantId, $branchId);
            $data = $this->normalizeBrokerCustomerFields($data);

            $trip = $this->tripRepository->create([
                ...$data,
                'driver_id' => $driverId,
                'trip_number' => $tripNumber,
                'start_station_id' => $data['start_station_id'] ?? null,
                'status' => TripStatus::Created->value,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->syncRouteLegs($trip, $routeLegs);
            $this->syncExpenses($trip, $expenses, $userId);
            $this->recalculateTotals($trip);

            TripStatusHistory::query()->create([
                'tenant_id' => $trip->tenant_id,
                'trip_id' => $trip->id,
                'to_status' => $trip->status->value ?? (string) $trip->status,
                'changed_at' => now(),
                'changed_by' => $userId,
            ]);

            return $this->tripRepository->findWithDetails($trip->id) ?? $trip->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Trip $trip, array $data, ?string $userId = null): Trip
    {
        return DB::transaction(function () use ($trip, $data, $userId): Trip {
            $this->tripValidation->assertTripIsEditable($trip);

            $oldStatus = $trip->status;
            $routeLegs = $data['route_legs'] ?? null;
            $expenses = $data['expenses'] ?? null;
            unset($data['route_legs'], $data['expenses'], $data['trip_number']);

            if (isset($data['status'])) {
                $this->tripValidation->assertStatusTransition($trip, (string) $data['status'], $data);
            }

            $vehicleId = (string) $trip->vehicle_id;
            $driverId = (string) $trip->driver_id;

            if ($routeLegs !== null) {
                $startStationId = isset($data['start_station_id'])
                    ? (int) $data['start_station_id']
                    : (int) $trip->start_station_id;

                $this->tripValidation->assertRouteLegsAreValid($routeLegs, $startStationId ?: null);
            }

            if ($expenses !== null) {
                $this->tripValidation->assertExpensesAreValid(
                    $expenses,
                    $data['start_at'] ?? $trip->start_at?->toDateTimeString(),
                    $data['end_at'] ?? $trip->end_at?->toDateTimeString(),
                );
            }

            $this->tripValidation->assertNoDuplicateTripWindow(
                $vehicleId,
                $data['start_at'] ?? $trip->start_at?->toDateTimeString(),
                $data['end_at'] ?? $trip->end_at?->toDateTimeString(),
                $trip->id,
            );
            $this->tripValidation->assertNoDuplicateDriverTripWindow(
                $driverId,
                $data['start_at'] ?? $trip->start_at?->toDateTimeString(),
                $data['end_at'] ?? $trip->end_at?->toDateTimeString(),
                $trip->id,
            );

            if (array_key_exists('substitute_driver_id', $data)) {
                $this->tripValidation->assertSubstituteDriverIsValid($data['substitute_driver_id'] ?? null, $driverId);
                $data['substitute_driver_id'] = $data['substitute_driver_id'] ?: null;
            }

            if (array_key_exists('customer_id', $data) || array_key_exists('broker_id', $data)) {
                $data = $this->normalizeBrokerCustomerFields($data);
            }

            $this->tripRepository->update($trip, [
                ...$data,
                'updated_by' => $userId,
            ]);

            if ($routeLegs !== null) {
                $trip->routeLegs()->delete();
                $this->syncRouteLegs($trip->fresh(), $routeLegs);
            }

            if ($expenses !== null) {
                $trip->expenses()->delete();
                $this->syncExpenses($trip->fresh(), $expenses, $userId);
            }

            $this->recalculateTotals($trip->fresh());

            $trip->refresh();

            if (
                isset($data['status'])
                && $oldStatus->value !== ($trip->status->value ?? (string) $trip->status)
            ) {
                $newStatus = $trip->status->value ?? (string) $trip->status;
                $changedAt = now();

                if ($newStatus === TripStatus::Cancelled->value && isset($data['cancelled_at'])) {
                    $changedAt = $data['cancelled_at'];
                }

                TripStatusHistory::query()->create([
                    'tenant_id' => $trip->tenant_id,
                    'trip_id' => $trip->id,
                    'from_status' => $oldStatus->value,
                    'to_status' => $newStatus,
                    'changed_at' => $changedAt,
                    'changed_by' => $userId,
                    'remarks' => $newStatus === TripStatus::Cancelled->value
                        ? ($data['cancellation_reason'] ?? null)
                        : null,
                ]);
            }

            return $this->tripRepository->findWithDetails($trip->id) ?? $trip;
        });
    }

    public function delete(Trip $trip, ?string $userId = null): void
    {
        $status = $trip->status instanceof TripStatus
            ? $trip->status
            : TripStatus::tryFrom((string) $trip->status);

        if (in_array($status, [TripStatus::InProgress, TripStatus::Completed], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'trip' => ['Trips in progress or completed cannot be deleted. Cancel the trip first if required.'],
            ]);
        }

        $this->tripRepository->delete($trip, $userId);
    }

    /**
     * @param  list<array<string, mixed>>  $routeLegs
     */
    private function syncRouteLegs(Trip $trip, array $routeLegs): void
    {
        $stationNames = Station::query()
            ->whereIn('id', collect($routeLegs)->flatMap(fn (array $leg): array => [
                $leg['from_station_id'] ?? null,
                $leg['to_station_id'] ?? null,
            ])->filter()->unique())
            ->pluck('name', 'id');

        foreach ($routeLegs as $index => $leg) {
            $routeType = strtoupper((string) ($leg['route_type'] ?? RouteType::Loaded->value));
            $freightAmount = (float) ($leg['freight_amount'] ?? 0);
            $advanceReceived = (float) ($leg['advance_received'] ?? 0);

            if ($routeType === RouteType::Empty->value) {
                $freightAmount = 0;
                $advanceReceived = 0;
            }

            $fromStationId = isset($leg['from_station_id']) ? (int) $leg['from_station_id'] : null;
            $toStationId = isset($leg['to_station_id']) ? (int) $leg['to_station_id'] : null;

            $fromCity = trim((string) ($leg['from_city'] ?? $stationNames->get($fromStationId) ?? ''));
            $fromAddress = trim((string) ($leg['from_address'] ?? ''));
            $toCity = trim((string) ($leg['to_city'] ?? $stationNames->get($toStationId) ?? ''));
            $toAddress = trim((string) ($leg['to_address'] ?? ''));

            TripRouteLeg::query()->create([
                'tenant_id' => $trip->tenant_id,
                'trip_id' => $trip->id,
                'customer_id' => isset($leg['customer_id']) && $leg['customer_id'] !== '' && $leg['customer_id'] !== null
                    ? (int) $leg['customer_id']
                    : null,
                'from_station_id' => $fromStationId,
                'to_station_id' => $toStationId,
                'from_city' => $fromCity ?: null,
                'from_address' => $fromAddress ?: null,
                'to_city' => $toCity ?: null,
                'to_address' => $toAddress ?: null,
                'from_location' => RouteLegLocationFormatter::compose(
                    $fromCity,
                    $fromAddress,
                    $leg['from_location'] ?? null,
                ),
                'to_location' => RouteLegLocationFormatter::compose(
                    $toCity,
                    $toAddress,
                    $leg['to_location'] ?? null,
                ),
                'route_type' => $routeType,
                'sequence_no' => (int) ($leg['sequence_no'] ?? ($index + 1)),
                'distance_km' => (float) ($leg['distance_km'] ?? 0),
                'goods_name' => $leg['goods_name'] ?? null,
                'consignor' => $leg['consignor'] ?? null,
                'consignee' => $leg['consignee'] ?? null,
                'freight_rate' => isset($leg['freight_rate']) ? (float) $leg['freight_rate'] : null,
                'weight_ton' => isset($leg['weight_ton']) ? (float) $leg['weight_ton'] : null,
                'invoice_number' => $leg['invoice_number'] ?? null,
                'lr_number' => $leg['lr_number'] ?? null,
                'remarks' => $leg['remarks'] ?? null,
                'freight_amount' => $freightAmount,
                'advance_received' => $advanceReceived,
                'balance_amount' => max($freightAmount - $advanceReceived, 0),
                'payment_status' => $this->resolvePaymentStatus($freightAmount, $advanceReceived)->value,
                'billing_status' => $leg['billing_status'] ?? 'unbilled',
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $expenses
     */
    private function syncExpenses(Trip $trip, array $expenses, ?string $userId): void
    {
        $legsBySequence = $trip->routeLegs()->get()->keyBy('sequence_no');

        foreach ($expenses as $expense) {
            $legId = $expense['trip_route_leg_id'] ?? null;

            if ($legId === null && isset($expense['route_leg_sequence'])) {
                $legId = $legsBySequence->get((int) $expense['route_leg_sequence'])?->id;
            }

            TripExpense::query()->create([
                'tenant_id' => $trip->tenant_id,
                'trip_id' => $trip->id,
                'trip_route_leg_id' => $legId,
                'expense_type' => strtoupper((string) $expense['expense_type']),
                'other_type' => strtoupper((string) $expense['expense_type']) === TripExpenseType::Other->value
                    ? ($expense['other_type'] ?? null)
                    : null,
                'expense_date' => $expense['expense_date'] ?? null,
                'amount' => (float) ($expense['amount'] ?? 0),
                'fuel_liters' => isset($expense['fuel_liters']) ? (float) $expense['fuel_liters'] : null,
                'fuel_rate' => isset($expense['fuel_rate']) ? (float) $expense['fuel_rate'] : null,
                'fuel_odometer_km' => isset($expense['fuel_odometer_km']) ? (float) $expense['fuel_odometer_km'] : null,
                'currency' => $expense['currency'] ?? 'INR',
                'notes' => $expense['notes'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    private function recalculateTotals(Trip $trip): void
    {
        $trip->load(['routeLegs', 'expenses']);

        $loadedKm = 0.0;
        $halfLoadedKm = 0.0;
        $emptyKm = 0.0;
        $freightTotal = 0.0;

        foreach ($trip->routeLegs as $leg) {
            $distance = (float) $leg->distance_km;
            $routeType = $leg->route_type instanceof RouteType
                ? $leg->route_type
                : RouteType::tryFrom((string) $leg->route_type);

            if ($routeType === RouteType::Empty) {
                $emptyKm += $distance;

                continue;
            }

            if ($routeType === RouteType::HalfLoaded) {
                $halfLoadedKm += $distance;
                $freightTotal += (float) $leg->freight_amount;

                continue;
            }

            $loadedKm += $distance;
            $freightTotal += (float) $leg->freight_amount;
        }

        $expenseTotal = (float) $trip->expenses->sum('amount');

        $trip->update([
            'total_distance_km' => $loadedKm + $halfLoadedKm + $emptyKm,
            'loaded_distance_km' => $loadedKm,
            'half_loaded_distance_km' => $halfLoadedKm,
            'empty_distance_km' => $emptyKm,
            'total_freight_amount' => $freightTotal,
            'total_expense_amount' => $expenseTotal,
        ]);
    }

    private function resolvePaymentStatus(float $freightAmount, float $advanceReceived): FreightPaymentStatus
    {
        if ($freightAmount <= 0) {
            return FreightPaymentStatus::Pending;
        }

        if ($advanceReceived >= $freightAmount) {
            return FreightPaymentStatus::Paid;
        }

        if ($advanceReceived > 0) {
            return FreightPaymentStatus::Partial;
        }

        return FreightPaymentStatus::Pending;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeBrokerCustomerFields(array $data): array
    {
        if (array_key_exists('customer_id', $data)) {
            $customerId = $data['customer_id'] !== null && $data['customer_id'] !== ''
                ? (int) $data['customer_id']
                : null;

            if ($customerId) {
                $customer = Customer::query()->find($customerId);
                $data['customer_id'] = $customer?->id;
                $data['customer_name'] = $customer?->name;
            } else {
                $data['customer_id'] = null;
                $data['customer_name'] = null;
            }
        }

        if (array_key_exists('broker_id', $data)) {
            $brokerId = $data['broker_id'] !== null && $data['broker_id'] !== ''
                ? (int) $data['broker_id']
                : null;

            if ($brokerId) {
                $broker = Broker::query()->find($brokerId);
                $data['broker_id'] = $broker?->id;
                $data['broker_name'] = $broker?->name;
            } else {
                $data['broker_id'] = null;
                $data['broker_name'] = null;
            }
        }

        return $data;
    }
}
