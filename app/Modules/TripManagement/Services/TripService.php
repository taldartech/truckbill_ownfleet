<?php

namespace App\Modules\TripManagement\Services;

use App\Broker;
use App\Customer;
use App\Enums\DropDeliveryStatus;
use App\Enums\FreightPaymentStatus;
use App\Enums\TripExpenseType;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Modules\TripManagement\Repositories\TripRepository;
use App\Station;
use App\Support\ArchivedTripGuard;
use App\Support\BranchContext;
use App\Support\CurrentTenant;
use App\Support\TripArchiveImpact;
use App\Support\TripNumberGenerator;
use App\Support\TyreStintCalculator;
use App\Trip;
use App\TripExpense;
use App\TripPickup;
use App\TripRouteLeg;
use App\TripStatusHistory;
use App\User;
use App\Vehicle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripService
{
    public function __construct(
        private readonly TripRepository $tripRepository,
        private readonly TripValidationService $tripValidation,
        private readonly TripNumberGenerator $tripNumberGenerator,
        private readonly VehicleTripEligibilityService $vehicleTripEligibility,
        private readonly TripSnapshotService $tripSnapshotService,
        private readonly TripAuditLogger $tripAuditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->tripRepository->paginate($perPage, $filters);
    }

    public function previewNextTripNumber(?string $startAt = null, ?int $sequence = null): array
    {
        return $this->tripNumberGenerator->preview(
            TripNumberGenerator::DOCUMENT_TRIP,
            CurrentTenant::id(),
            BranchContext::branchId(),
            $startAt,
            $sequence,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $userId = null): Trip
    {
        return DB::transaction(function () use ($data, $userId): Trip {
            $routeLegs = $data['route_legs'] ?? [];
            $pickups = $data['pickups'] ?? null;
            $expenses = $data['expenses'] ?? [];
            $tripSequence = array_key_exists('trip_sequence', $data) && $data['trip_sequence'] !== null && $data['trip_sequence'] !== ''
                ? (int) $data['trip_sequence']
                : null;
            unset($data['route_legs'], $data['pickups'], $data['expenses'], $data['trip_number'], $data['trip_sequence']);

            $tripType = strtoupper((string) ($data['trip_type'] ?? TripType::RoundTrip->value));
            $isMultiPoint = $tripType === TripType::MultiPoint->value;

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

            if ($isMultiPoint) {
                $customerId = isset($data['customer_id']) && $data['customer_id'] !== '' && $data['customer_id'] !== null
                    ? (int) $data['customer_id']
                    : null;
                $pickups = is_array($pickups) ? $pickups : [];
                $this->tripValidation->assertMultiPointPickupsAreValid($pickups, $customerId);
                $routeLegs = $this->expandMultiPointPickupsToRouteLegs($pickups, $customerId);
            } else {
                $this->tripValidation->assertRouteLegsAreValid(
                    $routeLegs,
                    isset($data['start_station_id']) ? (int) $data['start_station_id'] : null,
                    $tripType,
                );
            }

            $this->tripValidation->assertPositiveFreightTotal($routeLegs);
            $this->tripValidation->assertOptionalEndMeterReadingOnCreate($data, $vehicleId);
            $this->tripValidation->assertExpensesAreValid(
                $expenses,
                $data['start_at'] ?? null,
                $data['end_at'] ?? null,
                isset($data['start_meter_reading_km']) ? (float) $data['start_meter_reading_km'] : null,
                isset($data['end_meter_reading_km']) && $data['end_meter_reading_km'] !== ''
                    ? (float) $data['end_meter_reading_km']
                    : null,
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
            $tripNumber = $this->tripNumberGenerator->generate(
                $tenantId,
                $branchId,
                $data['start_at'] ?? null,
                $tripSequence,
            );
            $data = $this->normalizeBrokerCustomerFields($data);

            $tripPayload = [
                ...$data,
                'driver_id' => $driverId,
                'trip_number' => $tripNumber,
                'start_station_id' => $data['start_station_id'] ?? null,
                'start_meter_reading_km' => isset($data['start_meter_reading_km'])
                    && $data['start_meter_reading_km'] !== ''
                    && (float) $data['start_meter_reading_km'] > 0
                    ? round((float) $data['start_meter_reading_km'], 2)
                    : null,
                'end_meter_reading_km' => isset($data['end_meter_reading_km']) && $data['end_meter_reading_km'] !== ''
                    ? round((float) $data['end_meter_reading_km'], 2)
                    : null,
                'planned_mileage_km_per_liter' => isset($data['planned_mileage_km_per_liter']) && $data['planned_mileage_km_per_liter'] !== ''
                    ? round((float) $data['planned_mileage_km_per_liter'], 2)
                    : 0,
                'max_load_ton' => isset($data['max_load_ton']) && $data['max_load_ton'] !== ''
                    ? round((float) $data['max_load_ton'], 3)
                    : 0,
                'planned_diesel_liters' => isset($data['planned_diesel_liters']) && $data['planned_diesel_liters'] !== ''
                    ? round((float) $data['planned_diesel_liters'], 2)
                    : null,
                'diesel_rate_per_liter' => isset($data['diesel_rate_per_liter']) && $data['diesel_rate_per_liter'] !== ''
                    ? round((float) $data['diesel_rate_per_liter'], 2)
                    : null,
                'status' => TripStatus::Pending->value,
                'created_by' => $userId,
                'updated_by' => $userId,
            ];

            if (
                ! array_key_exists('planned_mileage_km_per_liter', $data)
                || $data['planned_mileage_km_per_liter'] === ''
                || $data['planned_mileage_km_per_liter'] === null
            ) {
                $this->tripSnapshotService->copyTruckDefaultsToTrip($vehicle, $tripPayload);
            }

            $trip = $this->tripRepository->create($tripPayload);

            if ($isMultiPoint) {
                $this->syncPickupsAndDrops($trip, is_array($pickups) ? $pickups : []);
            } else {
                $this->syncRouteLegs($trip, $routeLegs);
            }
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
    public function update(Trip $trip, array $data, ?User $user = null): Trip
    {
        ArchivedTripGuard::ensureModifiable($trip);

        return DB::transaction(function () use ($trip, $data, $user): Trip {
            $userId = $user?->id;
            $editScope = $this->tripValidation->resolveTripEditScope($trip, $user);
            $oldStatus = $trip->status;
            $routeLegs = $data['route_legs'] ?? null;
            $pickups = array_key_exists('pickups', $data) ? $data['pickups'] : null;
            $shouldSyncExpenses = array_key_exists('expenses', $data);
            $expenses = $shouldSyncExpenses ? $data['expenses'] : null;
            $statusChangeRequested = $this->isStatusChangeRequested($trip, $data);
            unset($data['route_legs'], $data['pickups'], $data['expenses'], $data['trip_number']);

            if ($statusChangeRequested && ! ($user?->hasAnyPermission(['trips.change_status', 'trips.edit', 'trips.manage']) ?? false)) {
                throw new AuthorizationException('You are not allowed to change trip status.');
            }

            $canEditTrips = $user?->hasPermission('trips.edit') ?? false;

            if ($statusChangeRequested && ! $canEditTrips) {
                $allowedStatusKeys = ['status', 'start_at', 'start_meter_reading_km', 'end_at', 'end_meter_reading_km'];
                if ($user?->hasPermission('trips.expenses') && $shouldSyncExpenses) {
                    // expenses are handled via $expenses
                }
                $nonStatusKeys = array_diff(array_keys($data), $allowedStatusKeys);

                if ($nonStatusKeys !== []) {
                    throw ValidationException::withMessages([
                        'trip' => ['Only trip status and expenses can be updated with your current permissions.'],
                    ]);
                }
            } elseif (! $statusChangeRequested) {

                if ($editScope === TripValidationService::EDIT_SCOPE_NONE) {
                    throw ValidationException::withMessages([
                        'trip' => [
                            $trip->hasInvoices()
                                ? 'This trip has invoices and cannot be edited.'
                                : 'Completed or cancelled trips cannot be edited.',
                        ],
                    ]);
                }

                if ($editScope === TripValidationService::EDIT_SCOPE_EXPENSES_ONLY) {
                    $expensesOnlyMessage = $trip->hasInvoices()
                        ? 'This trip has invoices and cannot be edited. Only expenses can be updated.'
                        : 'Only trip expenses can be updated with your current permissions.';

                    if (! $shouldSyncExpenses) {
                        throw ValidationException::withMessages([
                            'trip' => [$expensesOnlyMessage],
                        ]);
                    }

                    $nonExpenseKeys = array_keys($data);

                    if ($nonExpenseKeys !== []) {
                        throw ValidationException::withMessages([
                            'trip' => [$expensesOnlyMessage],
                        ]);
                    }
                }
            }

            if (isset($data['status'])) {
                $this->tripValidation->assertStatusTransition($trip, (string) $data['status'], $data);
            }

            $vehicleId = (string) $trip->vehicle_id;
            $driverId = (string) $trip->driver_id;
            $tripType = $trip->trip_type instanceof TripType
                ? $trip->trip_type->value
                : (string) ($trip->trip_type ?? TripType::RoundTrip->value);
            $isMultiPoint = $tripType === TripType::MultiPoint->value;

            if ($isMultiPoint && $pickups !== null) {
                $customerId = array_key_exists('customer_id', $data)
                    ? (isset($data['customer_id']) && $data['customer_id'] !== '' && $data['customer_id'] !== null
                        ? (int) $data['customer_id']
                        : null)
                    : ($trip->customer_id !== null ? (int) $trip->customer_id : null);

                $this->tripValidation->assertMultiPointPickupsAreValid(
                    is_array($pickups) ? $pickups : [],
                    $customerId,
                );
                $routeLegs = $this->expandMultiPointPickupsToRouteLegs(
                    is_array($pickups) ? $pickups : [],
                    $customerId,
                );
            } elseif ($routeLegs !== null) {
                $startStationId = isset($data['start_station_id'])
                    ? (int) $data['start_station_id']
                    : (int) $trip->start_station_id;

                $this->tripValidation->assertRouteLegsAreValid($routeLegs, $startStationId ?: null, $tripType);
            }

            if ($shouldSyncExpenses) {
                $existingExpenses = $trip->expenses()->get()->all();

                $this->tripValidation->assertExpensesAreValid(
                    $expenses,
                    $data['start_at'] ?? $trip->start_at?->toDateTimeString(),
                    $data['end_at'] ?? $trip->end_at?->toDateTimeString(),
                    array_key_exists('start_meter_reading_km', $data)
                        ? (float) $data['start_meter_reading_km']
                        : (float) ($trip->start_meter_reading_km ?? 0),
                    array_key_exists('end_meter_reading_km', $data)
                        ? ($data['end_meter_reading_km'] !== '' && $data['end_meter_reading_km'] !== null
                            ? (float) $data['end_meter_reading_km']
                            : null)
                        : ($trip->end_meter_reading_km !== null ? (float) $trip->end_meter_reading_km : null),
                    $existingExpenses,
                );
            }

            if ($editScope === TripValidationService::EDIT_SCOPE_FULL) {
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
            }

            if (array_key_exists('substitute_driver_id', $data)) {
                $this->tripValidation->assertSubstituteDriverIsValid($data['substitute_driver_id'] ?? null, $driverId);
                $data['substitute_driver_id'] = $data['substitute_driver_id'] ?: null;
            }

            if (array_key_exists('customer_id', $data) || array_key_exists('broker_id', $data)) {
                $data = $this->normalizeBrokerCustomerFields($data);
            }

            if (
                $editScope === TripValidationService::EDIT_SCOPE_FULL
                && (
                    array_key_exists('start_meter_reading_km', $data)
                    || array_key_exists('end_meter_reading_km', $data)
                )
            ) {
                $this->tripValidation->assertMeterReadingsOnUpdate($trip, $data);
            }

            if (array_key_exists('start_meter_reading_km', $data)) {
                $startMeter = $data['start_meter_reading_km'];
                $data['start_meter_reading_km'] = $startMeter !== null
                    && $startMeter !== ''
                    && (float) $startMeter > 0
                    ? round((float) $startMeter, 2)
                    : null;
            }

            if (array_key_exists('end_meter_reading_km', $data)) {
                $endMeter = $data['end_meter_reading_km'];

                $data['end_meter_reading_km'] = $endMeter === null || $endMeter === ''
                    ? null
                    : round((float) $endMeter, 2);
            }

            if (array_key_exists('planned_mileage_km_per_liter', $data)) {
                $data['planned_mileage_km_per_liter'] = $data['planned_mileage_km_per_liter'] === '' || $data['planned_mileage_km_per_liter'] === null
                    ? 0
                    : round((float) $data['planned_mileage_km_per_liter'], 2);
            }

            if (array_key_exists('max_load_ton', $data)) {
                $data['max_load_ton'] = $data['max_load_ton'] === '' || $data['max_load_ton'] === null
                    ? 0
                    : round((float) $data['max_load_ton'], 3);
            }

            if (array_key_exists('planned_diesel_liters', $data)) {
                $plannedDiesel = $data['planned_diesel_liters'];

                $data['planned_diesel_liters'] = $plannedDiesel === null || $plannedDiesel === ''
                    ? null
                    : round((float) $plannedDiesel, 2);
            }

            $this->tripRepository->update($trip, [
                ...$data,
                'updated_by' => $userId,
            ]);

            if ($isMultiPoint && $pickups !== null) {
                $this->tripValidation->assertRouteLegsCanBeUpdated($trip, $routeLegs ?? []);
                $this->replacePickupsAndDrops($trip, is_array($pickups) ? $pickups : []);
            } elseif ($routeLegs !== null) {
                $this->tripValidation->assertRouteLegsCanBeUpdated($trip, $routeLegs);

                if ($this->routeLegsStructureMatchesExisting($trip, $routeLegs)) {
                    $this->updateRouteLegsInPlace($trip, $routeLegs);
                } else {
                    $this->replaceRouteLegs($trip, $routeLegs);
                }
            }

            if ($shouldSyncExpenses) {
                $trip->expenses()->delete();
                $this->syncExpenses($trip->fresh(), $expenses ?? [], $userId);
            }

            $this->recalculateTotals($trip->fresh());

            $trip->refresh();

            $this->syncVehicleOdometerIfCompleted($trip);

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

    public function archive(Trip $trip, string $archiveReason, ?User $user = null): void
    {
        ArchivedTripGuard::ensureModifiable($trip);

        $status = $trip->status instanceof TripStatus
            ? $trip->status
            : TripStatus::tryFrom((string) $trip->status);

        if (! TripArchiveImpact::isArchivable($status, $user)) {
            throw ValidationException::withMessages([
                'trip' => [in_array($status, [TripStatus::Completed, TripStatus::Invoiced], true)
                    ? 'Completed or invoiced trips cannot be archived.'
                    : 'This trip cannot be archived or deleted.'],
            ]);
        }

        DB::transaction(function () use ($trip, $archiveReason, $user): void {
            $impact = TripArchiveImpact::forTrip($trip, $user);
            $userId = $user?->id;
            $archivedAt = now();

            $this->tripRepository->archive($trip, $userId, $archiveReason);

            $this->tripAuditLogger->log(
                eventType: 'trip.archived',
                trip: $trip,
                payload: [
                    'trip_number' => $trip->trip_number,
                    'archive_reason' => $archiveReason,
                    'archived_by' => [
                        'id' => $userId,
                        'name' => $user?->name,
                    ],
                    'archived_at' => $archivedAt->toIso8601String(),
                    'route_stops_count' => $impact['linked_records']['route_stops_count'],
                    'expenses_count' => $impact['linked_records']['expenses_count'],
                    'total_expense_amount' => $impact['linked_records']['total_expense_amount'],
                ],
                performedBy: $userId,
            );
        });
    }

    /**
     * @deprecated Use archive()
     */
    public function delete(Trip $trip, string $deletionReason, ?User $user = null): void
    {
        $this->archive($trip, $deletionReason, $user);
    }

    public function restore(Trip $trip, ?User $user = null): void
    {
        if (! $trip->trashed()) {
            throw ValidationException::withMessages([
                'trip' => ['This trip is not archived and cannot be restored.'],
            ]);
        }

        DB::transaction(function () use ($trip, $user): void {
            $previousArchiveReason = $trip->archive_reason;
            $userId = $user?->id;
            $restoredAt = now();

            $this->tripRepository->restore($trip);

            $this->tripAuditLogger->log(
                eventType: 'trip.restored',
                trip: $trip->fresh() ?? $trip,
                payload: [
                    'trip_number' => $trip->trip_number,
                    'previous_archive_reason' => $previousArchiveReason,
                    'restored_by' => [
                        'id' => $userId,
                        'name' => $user?->name,
                    ],
                    'restored_at' => $restoredAt->toIso8601String(),
                ],
                performedBy: $userId,
            );
        });
    }

    /**
     * @param  list<array<string, mixed>>  $routeLegs
     */
    private function replaceRouteLegs(Trip $trip, array $routeLegs): void
    {
        $existingBySequence = $trip->routeLegs()->get()->keyBy('sequence_no');

        $routeLegs = array_map(function (array $leg, int $index) use ($existingBySequence): array {
            $sequence = (int) ($leg['sequence_no'] ?? ($index + 1));
            $existing = $existingBySequence->get($sequence);

            if ($existing !== null && ! array_key_exists('advance_received', $leg)) {
                $leg['advance_received'] = $existing->advance_received;
            }

            return $leg;
        }, $routeLegs, array_keys($routeLegs));

        $expenseSequencesById = TripExpense::query()
            ->where('trip_id', $trip->id)
            ->whereNotNull('trip_route_leg_id')
            ->with('routeLeg:id,sequence_no')
            ->get()
            ->mapWithKeys(fn (TripExpense $expense): array => [
                $expense->id => $expense->routeLeg?->sequence_no,
            ])
            ->filter(fn (?int $sequence): bool => $sequence !== null);

        $trip->routeLegs()->delete();
        $this->syncRouteLegs($trip->fresh(), $routeLegs);

        if ($expenseSequencesById->isEmpty()) {
            return;
        }

        $newLegsBySequence = $trip->fresh()->routeLegs()->get()->keyBy('sequence_no');

        foreach ($expenseSequencesById as $expenseId => $sequenceNo) {
            $newLegId = $newLegsBySequence->get($sequenceNo)?->id;

            if ($newLegId === null) {
                continue;
            }

            TripExpense::query()
                ->where('id', $expenseId)
                ->update(['trip_route_leg_id' => $newLegId]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $routeLegs
     */
    private function routeLegsStructureMatchesExisting(Trip $trip, array $routeLegs): bool
    {
        $existing = $trip->routeLegs()->orderBy('sequence_no')->get();

        if ($existing->count() !== count($routeLegs)) {
            return false;
        }

        foreach ($routeLegs as $index => $leg) {
            $current = $existing[$index] ?? null;

            if ($current === null) {
                return false;
            }

            $expectedSequence = (int) ($leg['sequence_no'] ?? ($index + 1));

            if ((int) $current->sequence_no !== $expectedSequence) {
                return false;
            }

            if ((int) ($current->from_station_id ?? 0) !== (int) ($leg['from_station_id'] ?? 0)) {
                return false;
            }

            if ((int) ($current->to_station_id ?? 0) !== (int) ($leg['to_station_id'] ?? 0)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $routeLegs
     */
    private function updateRouteLegsInPlace(Trip $trip, array $routeLegs): void
    {
        $existingBySequence = $trip->routeLegs()->get()->keyBy('sequence_no');
        $stationNames = $this->resolveRouteLegStationNames($routeLegs);

        foreach ($routeLegs as $index => $leg) {
            $sequence = (int) ($leg['sequence_no'] ?? ($index + 1));
            $current = $existingBySequence->get($sequence);

            if ($current === null) {
                continue;
            }

            $current->update($this->buildRouteLegAttributes($trip, $leg, $current, $stationNames));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $routeLegs
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function resolveRouteLegStationNames(array $routeLegs): \Illuminate\Support\Collection
    {
        return Station::query()
            ->whereIn('id', collect($routeLegs)->flatMap(fn (array $leg): array => [
                $leg['from_station_id'] ?? null,
                $leg['to_station_id'] ?? null,
            ])->filter()->unique())
            ->pluck('name', 'id');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>|null  $stationNames
     * @return array<string, mixed>
     */
    private function buildRouteLegAttributes(
        Trip $trip,
        array $leg,
        ?TripRouteLeg $existing = null,
        ?\Illuminate\Support\Collection $stationNames = null,
    ): array {
        $weightTon = isset($leg['weight_ton']) ? (float) $leg['weight_ton'] : 0.0;
        $freightAmount = (float) ($leg['freight_amount'] ?? 0);
        $freightRate = isset($leg['freight_rate']) ? (float) $leg['freight_rate'] : 0.0;
        $advanceReceived = array_key_exists('advance_received', $leg)
            ? (float) $leg['advance_received']
            : (float) ($existing?->advance_received ?? 0);

        if ($weightTon <= 0 && $freightAmount > 0 && $freightRate > 0) {
            $weightTon = round($freightAmount / $freightRate, 3);
        }

        if ($weightTon <= 0 && $freightAmount <= 0) {
            $advanceReceived = 0;
        }

        $fromStationId = isset($leg['from_station_id']) ? (int) $leg['from_station_id'] : null;
        $toStationId = isset($leg['to_station_id']) ? (int) $leg['to_station_id'] : null;

        $stationNames ??= $this->resolveRouteLegStationNames([$leg]);

        $fromCity = trim((string) ($leg['from_city'] ?? $stationNames->get($fromStationId) ?? ''));
        $fromAddress = trim((string) ($leg['from_address'] ?? ''));
        $toCity = trim((string) ($leg['to_city'] ?? $stationNames->get($toStationId) ?? ''));
        $toAddress = trim((string) ($leg['to_address'] ?? ''));

        return [
            'customer_id' => isset($leg['customer_id']) && $leg['customer_id'] !== '' && $leg['customer_id'] !== null
                ? (int) $leg['customer_id']
                : null,
            'from_station_id' => $fromStationId,
            'to_station_id' => $toStationId,
            'from_city' => $fromCity ?: null,
            'from_address' => $fromAddress ?: null,
            'to_city' => $toCity ?: null,
            'to_address' => $toAddress ?: null,
            'goods_name' => $leg['goods_name'] ?? null,
            'pickup_date' => array_key_exists('pickup_date', $leg)
                ? (
                    isset($leg['pickup_date']) && $leg['pickup_date'] !== '' && $leg['pickup_date'] !== null
                        ? (string) $leg['pickup_date']
                        : null
                )
                : ($existing?->pickup_date?->format('Y-m-d')),
            'no_of_units' => array_key_exists('no_of_units', $leg)
                ? (
                    isset($leg['no_of_units']) && $leg['no_of_units'] !== '' && $leg['no_of_units'] !== null
                        ? max(0, (int) $leg['no_of_units'])
                        : null
                )
                : $existing?->no_of_units,
            'ewaybill_no' => array_key_exists('ewaybill_no', $leg)
                ? (
                    isset($leg['ewaybill_no']) && trim((string) $leg['ewaybill_no']) !== ''
                        ? trim((string) $leg['ewaybill_no'])
                        : null
                )
                : $existing?->ewaybill_no,
            'ewaybill_expiry' => array_key_exists('ewaybill_expiry', $leg)
                ? (
                    isset($leg['ewaybill_expiry']) && $leg['ewaybill_expiry'] !== '' && $leg['ewaybill_expiry'] !== null
                        ? (string) $leg['ewaybill_expiry']
                        : null
                )
                : ($existing?->ewaybill_expiry?->format('Y-m-d')),
            'goods_value' => array_key_exists('goods_value', $leg)
                ? (
                    isset($leg['goods_value']) && $leg['goods_value'] !== '' && $leg['goods_value'] !== null
                        ? max(0, round((float) $leg['goods_value'], 2))
                        : null
                )
                : $existing?->goods_value,
            'consignor' => $leg['consignor'] ?? null,
            'consignee' => $leg['consignee'] ?? null,
            'distance_km' => isset($leg['distance_km']) && $leg['distance_km'] !== '' && $leg['distance_km'] !== null
                ? round((float) $leg['distance_km'], 2)
                : ($existing !== null ? round((float) ($existing->distance_km ?? 0), 2) : 0),
            'freight_rate' => isset($leg['freight_rate']) ? (float) $leg['freight_rate'] : null,
            'weight_ton' => $weightTon > 0 ? $weightTon : null,
            'expected_mileage_km_per_liter' => isset($leg['expected_mileage_km_per_liter']) && $leg['expected_mileage_km_per_liter'] !== '' && $leg['expected_mileage_km_per_liter'] !== null
                ? round((float) $leg['expected_mileage_km_per_liter'], 2)
                : null,
            'invoice_number' => $leg['invoice_number'] ?? null,
            'lr_number' => $leg['lr_number'] ?? null,
            'remarks' => $leg['remarks'] ?? null,
            'freight_amount' => $freightAmount,
            'advance_received' => $advanceReceived,
            'balance_amount' => max($freightAmount - $advanceReceived, 0),
            'payment_status' => $this->resolvePaymentStatus($freightAmount, $advanceReceived)->value,
            'billing_status' => $leg['billing_status'] ?? ($existing?->billing_status ?? 'unbilled'),
            'trip_pickup_id' => $leg['trip_pickup_id'] ?? $existing?->trip_pickup_id,
            'drop_code' => array_key_exists('drop_code', $leg)
                ? (
                    isset($leg['drop_code']) && trim((string) $leg['drop_code']) !== ''
                        ? strtoupper(trim((string) $leg['drop_code']))
                        : null
                )
                : $existing?->drop_code,
            'delivery_status' => array_key_exists('delivery_status', $leg)
                ? ($leg['delivery_status'] ?? null)
                : ($existing?->delivery_status?->value ?? $existing?->delivery_status),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pickups
     * @return list<array<string, mixed>>
     */
    private function expandMultiPointPickupsToRouteLegs(array $pickups, ?int $customerId): array
    {
        $routeLegs = [];
        $sequence = 1;
        $dropSequence = 1;

        foreach ($pickups as $pickupIndex => $pickup) {
            if (! is_array($pickup)) {
                continue;
            }

            $pickupCode = strtoupper(trim((string) ($pickup['pickup_code'] ?? '')));
            if ($pickupCode === '') {
                $pickupCode = sprintf('P%03d', $pickupIndex + 1);
            }

            $fromStationId = isset($pickup['station_id']) ? (int) $pickup['station_id'] : null;
            $drops = is_array($pickup['drops'] ?? null) ? $pickup['drops'] : [];

            foreach ($drops as $drop) {
                if (! is_array($drop)) {
                    continue;
                }

                $dropCode = strtoupper(trim((string) ($drop['drop_code'] ?? '')));
                if ($dropCode === '') {
                    $dropCode = sprintf('D%03d', $dropSequence);
                }
                $dropSequence++;

                $routeLegs[] = [
                    ...$drop,
                    'from_station_id' => $fromStationId,
                    'to_station_id' => $drop['to_station_id'] ?? null,
                    'from_address' => $drop['from_address'] ?? ($pickup['from_address'] ?? null),
                    'consignor' => $drop['consignor'] ?? ($pickup['consignor'] ?? null),
                    'goods_name' => $drop['goods_name'] ?? ($pickup['goods_name'] ?? null),
                    'pickup_date' => $drop['pickup_date'] ?? ($pickup['pickup_date'] ?? null),
                    'no_of_units' => $drop['no_of_units'] ?? ($pickup['no_of_units'] ?? null),
                    'goods_value' => $drop['goods_value'] ?? ($pickup['goods_value'] ?? null),
                    'ewaybill_no' => $drop['ewaybill_no'] ?? ($pickup['ewaybill_no'] ?? null),
                    'ewaybill_expiry' => $drop['ewaybill_expiry'] ?? ($pickup['ewaybill_expiry'] ?? null),
                    'customer_id' => $customerId,
                    'sequence_no' => $sequence++,
                    'pickup_code' => $pickupCode,
                    'drop_code' => $dropCode,
                    'delivery_status' => DropDeliveryStatus::Delivered->value,
                ];
            }
        }

        return $routeLegs;
    }

    /**
     * @param  list<array<string, mixed>>  $pickups
     */
    private function syncPickupsAndDrops(Trip $trip, array $pickups): void
    {
        $customerId = $trip->customer_id !== null ? (int) $trip->customer_id : null;
        $stationIds = collect($pickups)
            ->flatMap(function (array $pickup): array {
                $ids = [$pickup['station_id'] ?? null];
                foreach ($pickup['drops'] ?? [] as $drop) {
                    if (is_array($drop)) {
                        $ids[] = $drop['to_station_id'] ?? null;
                    }
                }

                return $ids;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        $stationNames = Station::query()->whereIn('id', $stationIds)->pluck('name', 'id');
        $dropSequence = 1;
        $legSequence = 1;

        foreach ($pickups as $pickupIndex => $pickup) {
            if (! is_array($pickup)) {
                continue;
            }

            $pickupCode = strtoupper(trim((string) ($pickup['pickup_code'] ?? '')));
            if ($pickupCode === '') {
                $pickupCode = sprintf('P%03d', $pickupIndex + 1);
            }

            $stationId = (int) $pickup['station_id'];
            $pickupModel = TripPickup::query()->create([
                'tenant_id' => $trip->tenant_id,
                'branch_id' => $trip->branch_id,
                'trip_id' => $trip->id,
                'pickup_code' => $pickupCode,
                'sequence_no' => (int) ($pickup['sequence_no'] ?? ($pickupIndex + 1)),
                'station_id' => $stationId,
                'consignor' => $pickup['consignor'] ?? null,
                'from_address' => isset($pickup['from_address']) && trim((string) $pickup['from_address']) !== ''
                    ? trim((string) $pickup['from_address'])
                    : null,
                'goods_name' => $pickup['goods_name'] ?? null,
                'no_of_units' => isset($pickup['no_of_units']) && $pickup['no_of_units'] !== '' && $pickup['no_of_units'] !== null
                    ? max(0, (int) $pickup['no_of_units'])
                    : null,
                'goods_value' => isset($pickup['goods_value']) && $pickup['goods_value'] !== '' && $pickup['goods_value'] !== null
                    ? max(0, round((float) $pickup['goods_value'], 2))
                    : null,
                'ewaybill_no' => isset($pickup['ewaybill_no']) && trim((string) $pickup['ewaybill_no']) !== ''
                    ? trim((string) $pickup['ewaybill_no'])
                    : null,
                'ewaybill_expiry' => isset($pickup['ewaybill_expiry']) && $pickup['ewaybill_expiry'] !== '' && $pickup['ewaybill_expiry'] !== null
                    ? (string) $pickup['ewaybill_expiry']
                    : null,
                'pickup_date' => isset($pickup['pickup_date']) && $pickup['pickup_date'] !== '' && $pickup['pickup_date'] !== null
                    ? (string) $pickup['pickup_date']
                    : null,
                'weight_ton' => round((float) ($pickup['weight_ton'] ?? 0), 3),
            ]);

            foreach ($pickup['drops'] ?? [] as $drop) {
                if (! is_array($drop)) {
                    continue;
                }

                $dropCode = strtoupper(trim((string) ($drop['drop_code'] ?? '')));
                if ($dropCode === '') {
                    $dropCode = sprintf('D%03d', $dropSequence);
                }
                $dropSequence++;

                $legPayload = [
                    ...$drop,
                    'from_station_id' => $stationId,
                    'to_station_id' => $drop['to_station_id'] ?? null,
                    'from_city' => $drop['from_city'] ?? $stationNames->get($stationId),
                    'from_address' => $drop['from_address'] ?? ($pickup['from_address'] ?? null),
                    'consignor' => $drop['consignor'] ?? ($pickup['consignor'] ?? null),
                    'goods_name' => $drop['goods_name'] ?? ($pickup['goods_name'] ?? null),
                    'pickup_date' => $drop['pickup_date'] ?? ($pickup['pickup_date'] ?? null),
                    'no_of_units' => $drop['no_of_units'] ?? ($pickup['no_of_units'] ?? null),
                    'goods_value' => $drop['goods_value'] ?? ($pickup['goods_value'] ?? null),
                    'ewaybill_no' => $drop['ewaybill_no'] ?? ($pickup['ewaybill_no'] ?? null),
                    'ewaybill_expiry' => $drop['ewaybill_expiry'] ?? ($pickup['ewaybill_expiry'] ?? null),
                    'customer_id' => $customerId,
                    'trip_pickup_id' => $pickupModel->id,
                    'drop_code' => $dropCode,
                    'delivery_status' => DropDeliveryStatus::Delivered->value,
                ];

                TripRouteLeg::query()->create([
                    'tenant_id' => $trip->tenant_id,
                    'trip_id' => $trip->id,
                    'sequence_no' => $legSequence++,
                    ...$this->buildRouteLegAttributes($trip, $legPayload, null, $stationNames),
                ]);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $pickups
     */
    private function replacePickupsAndDrops(Trip $trip, array $pickups): void
    {
        $expenseSequencesById = TripExpense::query()
            ->where('trip_id', $trip->id)
            ->whereNotNull('trip_route_leg_id')
            ->with('routeLeg:id,sequence_no')
            ->get()
            ->mapWithKeys(fn (TripExpense $expense): array => [
                $expense->id => $expense->routeLeg?->sequence_no,
            ])
            ->filter(fn (?int $sequence): bool => $sequence !== null);

        $trip->routeLegs()->delete();
        $trip->pickups()->delete();
        $this->syncPickupsAndDrops($trip->fresh(), $pickups);

        if ($expenseSequencesById->isEmpty()) {
            return;
        }

        $newLegsBySequence = $trip->fresh()->routeLegs()->get()->keyBy('sequence_no');

        foreach ($expenseSequencesById as $expenseId => $sequenceNo) {
            $newLegId = $newLegsBySequence->get($sequenceNo)?->id;

            if ($newLegId === null) {
                continue;
            }

            TripExpense::query()
                ->where('id', $expenseId)
                ->update(['trip_route_leg_id' => $newLegId]);
        }
    }

    private function syncRouteLegs(Trip $trip, array $routeLegs): void
    {
        $stationNames = $this->resolveRouteLegStationNames($routeLegs);

        foreach ($routeLegs as $index => $leg) {
            TripRouteLeg::query()->create([
                'tenant_id' => $trip->tenant_id,
                'trip_id' => $trip->id,
                'sequence_no' => (int) ($leg['sequence_no'] ?? ($index + 1)),
                ...$this->buildRouteLegAttributes($trip, $leg, null, $stationNames),
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
            $expenseType = strtoupper((string) $expense['expense_type']);

            if ($expenseType === TripExpenseType::Diesel->value && ! TripValidationService::isActiveFuelRow($expense)) {
                continue;
            }

            $legId = $expense['trip_route_leg_id'] ?? null;

            if ($legId === null && isset($expense['route_leg_sequence'])) {
                $legId = $legsBySequence->get((int) $expense['route_leg_sequence'])?->id;
            }

            if (
                $legId === null
                && $legsBySequence->count() === 1
                && in_array($expenseType, [
                    TripExpenseType::LoadingCharges->value,
                    TripExpenseType::UnloadingCharges->value,
                ], true)
            ) {
                $legId = $legsBySequence->first()?->id;
            }

            if ($expenseType === TripExpenseType::Diesel->value) {
                $amount = round((float) $expense['fuel_liters'] * (float) $expense['fuel_rate'], 2);
                $fuelOdometer = isset($expense['fuel_odometer_km']) ? (int) $expense['fuel_odometer_km'] : null;
                $fuelLiters = isset($expense['fuel_liters']) ? round((float) $expense['fuel_liters'], 2) : null;
                $fuelRate = isset($expense['fuel_rate']) ? round((float) $expense['fuel_rate'], 2) : null;
            } else {
                $amount = (float) ($expense['amount'] ?? 0);
                $fuelOdometer = null;
                $fuelLiters = null;
                $fuelRate = null;
            }

            TripExpense::query()->create([
                'tenant_id' => $trip->tenant_id,
                'trip_id' => $trip->id,
                'trip_route_leg_id' => $legId,
                'expense_type' => $expenseType,
                'other_type' => $expenseType === TripExpenseType::Other->value
                    ? ($expense['other_type'] ?? null)
                    : null,
                'expense_date' => $expense['expense_date'] ?? null,
                'amount' => $amount,
                'fuel_liters' => $fuelLiters,
                'fuel_rate' => $fuelRate,
                'fuel_odometer_km' => $fuelOdometer,
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

        $freightTotal = 0.0;

        foreach ($trip->routeLegs as $leg) {
            $freightTotal += (float) $leg->freight_amount;
        }

        $expenseTotal = (float) $trip->expenses->sum('amount');

        $startMeter = (float) ($trip->start_meter_reading_km ?? 0);
        $endMeter = $trip->end_meter_reading_km !== null
            ? (float) $trip->end_meter_reading_km
            : null;

        $totalDistanceKm = $endMeter !== null
            ? max(0, round($endMeter - $startMeter, 2))
            : 0.0;

        $trip->update([
            'total_distance_km' => $totalDistanceKm,
            'loaded_distance_km' => 0,
            'empty_distance_km' => 0,
            'total_freight_amount' => $freightTotal,
            'total_expense_amount' => $expenseTotal,
        ]);
    }

    private function syncVehicleOdometerIfCompleted(Trip $trip): void
    {
        $status = $trip->status instanceof TripStatus
            ? $trip->status
            : TripStatus::tryFrom((string) $trip->status);

        if (! in_array($status, [TripStatus::Completed, TripStatus::Cancelled], true)) {
            return;
        }

        $endMeter = $trip->end_meter_reading_km;

        if ($endMeter === null) {
            return;
        }

        $vehicle = Vehicle::query()->find($trip->vehicle_id);

        if ($vehicle === null) {
            return;
        }

        app(TyreStintCalculator::class)->syncVehicleOdometer($vehicle, (float) $endMeter);
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function isStatusChangeRequested(Trip $trip, array $data): bool
    {
        if (! isset($data['status'])) {
            return false;
        }

        $currentStatus = $trip->status instanceof TripStatus
            ? $trip->status->value
            : strtoupper((string) $trip->status);

        return strtoupper((string) $data['status']) !== $currentStatus;
    }
}
