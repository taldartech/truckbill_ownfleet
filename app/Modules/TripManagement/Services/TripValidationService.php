<?php

namespace App\Modules\TripManagement\Services;

use App\Driver;
use App\DriverAssignment;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Support\RoleHierarchy;
use App\Trip;
use App\TripExpense;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TripValidationService
{
    public const EDIT_SCOPE_FULL = 'full';

    public const EDIT_SCOPE_EXPENSES_ONLY = 'expenses_only';

    public const EDIT_SCOPE_NONE = 'none';

    public const MAX_FUEL_LITERS = 5000;

    public const MAX_FUEL_RATE = 1000;

    public const FUEL_DECIMAL_PLACES = 2;

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_STATUS_TRANSITIONS = [
        'CREATED' => ['IN_PROGRESS'],
        'IN_PROGRESS' => ['COMPLETED', 'CANCELLED'],
        'COMPLETED' => [],
        'CANCELLED' => [],
    ];

    public function resolveTripEditScope(Trip $trip, ?User $user): string
    {
        $current = $this->tripStatusValue($trip);

        if ($trip->hasInvoices()) {
            if (
                $user !== null
                && (
                    RoleHierarchy::isSuperAdminRole((int) $user->user_role_id)
                    || in_array((int) $user->user_role_id, [UserRole::ADMIN, UserRole::MODERATOR], true)
                )
                && $current === TripStatus::Completed->value
            ) {
                return self::EDIT_SCOPE_EXPENSES_ONLY;
            }

            return self::EDIT_SCOPE_NONE;
        }

        if (! in_array($current, [TripStatus::Completed->value, TripStatus::Cancelled->value], true)) {
            return self::EDIT_SCOPE_FULL;
        }

        if ($user !== null && RoleHierarchy::isSuperAdminRole((int) $user->user_role_id)) {
            return self::EDIT_SCOPE_FULL;
        }

        if (
            $user !== null
            && in_array((int) $user->user_role_id, [UserRole::ADMIN, UserRole::MODERATOR], true)
            && $current === TripStatus::Completed->value
        ) {
            return self::EDIT_SCOPE_EXPENSES_ONLY;
        }

        return self::EDIT_SCOPE_NONE;
    }

    public function assertTripIsEditable(Trip $trip, ?User $user = null, string $requiredScope = self::EDIT_SCOPE_FULL): void
    {
        $scope = $this->resolveTripEditScope($trip, $user);

        if ($scope === self::EDIT_SCOPE_NONE) {
            throw ValidationException::withMessages([
                'trip' => [
                    $trip->hasInvoices()
                        ? 'This trip has invoices and cannot be edited.'
                        : 'Completed or cancelled trips cannot be edited.',
                ],
            ]);
        }

        if ($requiredScope === self::EDIT_SCOPE_FULL && $scope !== self::EDIT_SCOPE_FULL) {
            throw ValidationException::withMessages([
                'trip' => [
                    $trip->hasInvoices()
                        ? 'This trip has invoices and cannot be edited. Only expenses can be updated.'
                        : 'Only trip expenses can be edited on completed trips.',
                ],
            ]);
        }
    }

    public function findOverlappingOpenTrip(
        ?string $vehicleId = null,
        ?string $driverId = null,
        ?string $startAt = null,
        ?string $endAt = null,
        ?string $ignoreTripId = null,
    ): ?Trip {
        if ($startAt === null || ($vehicleId === null && $driverId === null)) {
            return null;
        }

        [$windowStart, $windowEnd] = $this->resolveTripWindow($startAt, $endAt);

        $query = Trip::query()
            ->whereIn('status', [
                TripStatus::Created->value,
                TripStatus::InProgress->value,
            ]);

        if ($vehicleId !== null) {
            $query->where('vehicle_id', $vehicleId);
        }

        if ($driverId !== null) {
            $query->where('driver_id', $driverId);
        }

        if ($ignoreTripId !== null) {
            $query->where('id', '!=', $ignoreTripId);
        }

        return $query
            ->whereRaw('DATE(start_at) <= ?', [$windowEnd])
            ->where(function ($overlapQuery) use ($windowStart): void {
                $overlapQuery
                    ->whereNull('end_at')
                    ->orWhereRaw('DATE(end_at) >= ?', [$windowStart]);
            })
            ->first();
    }

    public function assertDriverAssignedToVehicle(string $vehicleId, string $driverId, ?string $tripStartAt): void
    {
        if ($tripStartAt === null) {
            throw ValidationException::withMessages([
                'start_at' => ['Trip start date is required to validate driver assignment.'],
            ]);
        }

        $date = Carbon::parse($tripStartAt)->toDateString();

        $assigned = DriverAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->where('driver_id', $driverId)
            ->activePrimaryOn($date)
            ->exists();

        if (! $assigned) {
            throw ValidationException::withMessages([
                'driver_id' => ['No primary driver is assigned to this truck on the trip start date.'],
                'start_at' => ['Adjust the trip start date or update the truck\'s primary driver assignment.'],
            ]);
        }
    }

    public function assertDriverIsActive(string $driverId): void
    {
        $driver = Driver::query()->find($driverId);

        if ($driver === null || ! $driver->is_active) {
            throw ValidationException::withMessages([
                'driver_id' => ['Selected driver is inactive or not found.'],
            ]);
        }
    }

    public function assertSubstituteDriverIsValid(?string $substituteDriverId, string $primaryDriverId): void
    {
        if ($substituteDriverId === null || $substituteDriverId === '') {
            return;
        }

        if ((string) $substituteDriverId === (string) $primaryDriverId) {
            throw ValidationException::withMessages([
                'substitute_driver_id' => ['Substitute driver must be different from the primary driver.'],
            ]);
        }

        $driver = Driver::query()->find($substituteDriverId);

        if ($driver === null || ! $driver->is_active) {
            throw ValidationException::withMessages([
                'substitute_driver_id' => ['Selected substitute driver is inactive or not found.'],
            ]);
        }
    }

    public function assertVehicleExists(string $vehicleId): void
    {
        $vehicle = Vehicle::query()->find($vehicleId);

        if ($vehicle === null || ! $vehicle->is_active) {
            throw ValidationException::withMessages([
                'vehicle_id' => ['Selected truck is inactive or not found.'],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $routeLegs
     */
    public function assertRouteLegsAreValid(
        array $routeLegs,
        ?int $startStationId = null,
        string $tripType = TripType::RoundTrip->value,
    ): void {
        $isRoundTrip = strtoupper($tripType) === TripType::RoundTrip->value;
        $tripLabel = $isRoundTrip ? 'round trip' : 'trip';

        if ($routeLegs === []) {
            throw ValidationException::withMessages([
                'route_legs' => ["Add at least one route stop for this {$tripLabel}."],
            ]);
        }

        if ($startStationId === null) {
            throw ValidationException::withMessages([
                'start_station_id' => ["Select the start station for this {$tripLabel}."],
            ]);
        }

        $hasLoadedLeg = false;
        $errors = [];
        $lastIndex = count($routeLegs) - 1;

        foreach ($routeLegs as $index => $leg) {
            $legNo = $index + 1;
            $fromStationId = isset($leg['from_station_id']) ? (int) $leg['from_station_id'] : null;
            $toStationId = isset($leg['to_station_id']) ? (int) $leg['to_station_id'] : null;
            $weightTon = isset($leg['weight_ton']) ? (float) $leg['weight_ton'] : 0.0;
            $freightAmount = (float) ($leg['freight_amount'] ?? 0);

            if ($fromStationId === null) {
                $errors["route_legs.{$index}.from_station_id"] = ["Stop {$legNo}: from station is required."];
            }

            if ($toStationId === null) {
                $errors["route_legs.{$index}.to_station_id"] = ["Stop {$legNo}: to station is required."];
            }

            if ($fromStationId !== null && $toStationId !== null && $fromStationId === $toStationId) {
                $errors["route_legs.{$index}.to_station_id"] = ["Stop {$legNo}: from and to station cannot be the same."];
            }

            if ($index === 0 && $fromStationId !== null && $fromStationId !== $startStationId) {
                $errors['route_legs.0.from_station_id'] = ['First stop must start from the selected start station.'];
            }

            if ($weightTon > 0) {
                $hasLoadedLeg = true;
                $advance = (float) ($leg['advance_received'] ?? 0);

                if ($advance > $freightAmount) {
                    $errors["route_legs.{$index}.advance_received"] = [
                        "Stop {$legNo}: advance cannot exceed freight amount.",
                    ];
                }
            } elseif ($freightAmount > 0) {
                $hasLoadedLeg = true;
            }
        }

        if ($isRoundTrip) {
            $lastLeg = $routeLegs[$lastIndex] ?? null;
            $lastToStationId = isset($lastLeg['to_station_id']) ? (int) $lastLeg['to_station_id'] : null;

            if ($lastToStationId !== null && $lastToStationId !== $startStationId) {
                $errors['route_legs'] = ['Round trip must end at the start station. Add a return leg to complete the round.'];
            }
        }

        if (! $hasLoadedLeg) {
            $errors['route_legs'] = ['At least one stop with load weight or freight amount is required.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $routeLegs
     */
    /**
     * @param  array<string, mixed>  $expense
     */
    public static function isActiveFuelRow(array $expense): bool
    {
        $odometer = $expense['fuel_odometer_km'] ?? null;
        $liters = $expense['fuel_liters'] ?? null;
        $rate = $expense['fuel_rate'] ?? null;

        return ($odometer !== null && $odometer !== '')
            || ($liters !== null && $liters !== '')
            || ($rate !== null && $rate !== '');
    }

    /**
     * @param  list<array<string, mixed>>  $expenses
     * @param  list<TripExpense>|null  $existingExpenses
     */
    public function assertExpensesAreValid(
        array $expenses,
        ?string $tripStartAt,
        ?string $tripEndAt = null,
        ?float $startMeterReadingKm = null,
        ?float $endMeterReadingKm = null,
        ?array $existingExpenses = null,
    ): void {
        if ($expenses === []) {
            return;
        }

        if ($tripStartAt === null) {
            throw ValidationException::withMessages([
                'start_at' => ['Trip start date is required to validate expense dates.'],
            ]);
        }

        $tripStart = Carbon::parse($tripStartAt)->toDateString();
        $tripEnd = $tripEndAt !== null && $tripEndAt !== ''
            ? Carbon::parse($tripEndAt)->toDateString()
            : null;
        $startMeter = $startMeterReadingKm ?? 0.0;
        $endMeter = $endMeterReadingKm;
        $legacyFuelSignatures = $this->buildLegacyFuelSignatures($existingExpenses ?? []);

        $errors = [];

        foreach ($expenses as $index => $expense) {
            $expenseType = strtoupper((string) ($expense['expense_type'] ?? ''));
            $isActiveDiesel = $expenseType === 'DIESEL' && self::isActiveFuelRow($expense);
            $isActiveNonDiesel = $expenseType !== 'DIESEL' && (float) ($expense['amount'] ?? 0) > 0;

            if (! $isActiveDiesel && ! $isActiveNonDiesel) {
                continue;
            }

            $expenseDate = $expense['expense_date'] ?? null;

            if ($expenseDate === null || $expenseDate === '') {
                $errors["expenses.{$index}.expense_date"] = ['Expense date is required.'];

                continue;
            }

            $expenseDay = Carbon::parse($expenseDate)->toDateString();

            if ($expenseDay < $tripStart) {
                $errors["expenses.{$index}.expense_date"] = ['Expense date cannot be before the trip start date.'];
            }

            if ($tripEnd !== null && $expenseDay > $tripEnd) {
                $errors["expenses.{$index}.expense_date"] = ['Expense date cannot be after the trip end date.'];
            }

            if ($isActiveDiesel) {
                $skipMeterRange = in_array($this->fuelRowSignature($expense), $legacyFuelSignatures, true);
                $this->collectDieselExpenseErrors(
                    $expense,
                    $index,
                    $startMeter,
                    $endMeter,
                    $skipMeterRange,
                    $errors,
                );
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  list<TripExpense>  $existingExpenses
     * @return list<string>
     */
    private function buildLegacyFuelSignatures(array $existingExpenses): array
    {
        $signatures = [];

        foreach ($existingExpenses as $existing) {
            $existingType = $existing->expense_type instanceof \BackedEnum
                ? $existing->expense_type->value
                : (string) $existing->expense_type;

            if (strtoupper($existingType) !== 'DIESEL') {
                continue;
            }

            $row = [
                'fuel_odometer_km' => $existing->fuel_odometer_km,
                'fuel_liters' => $existing->fuel_liters,
                'fuel_rate' => $existing->fuel_rate,
            ];

            if (! self::isActiveFuelRow($row)) {
                continue;
            }

            $signatures[] = $this->fuelRowSignature($row);
        }

        return $signatures;
    }

    /**
     * @param  array<string, mixed>  $expense
     */
    private function fuelRowSignature(array $expense): string
    {
        $odometer = $expense['fuel_odometer_km'] ?? null;
        $liters = $expense['fuel_liters'] ?? null;
        $rate = $expense['fuel_rate'] ?? null;

        return sprintf(
            '%s|%s|%s',
            $odometer !== null && $odometer !== '' ? (string) (int) $odometer : '',
            $liters !== null && $liters !== '' ? (string) round((float) $liters, self::FUEL_DECIMAL_PLACES) : '',
            $rate !== null && $rate !== '' ? (string) round((float) $rate, self::FUEL_DECIMAL_PLACES) : '',
        );
    }

    /**
     * @param  array<string, mixed>  $expense
     * @param  array<string, list<string>>  $errors
     */
    private function collectDieselExpenseErrors(
        array $expense,
        int $index,
        float $startMeter,
        ?float $endMeter,
        bool $skipMeterRange,
        array &$errors,
    ): void {
        $fuelOdometer = $expense['fuel_odometer_km'] ?? null;
        $fuelLiters = $expense['fuel_liters'] ?? null;
        $fuelRate = $expense['fuel_rate'] ?? null;

        if ($fuelOdometer === null || $fuelOdometer === '') {
            $errors["expenses.{$index}.fuel_odometer_km"] = ['Meter reading is required.'];
        } elseif (! is_numeric($fuelOdometer) || (float) $fuelOdometer != (int) $fuelOdometer) {
            $errors["expenses.{$index}.fuel_odometer_km"] = ['Meter reading must be a whole number.'];
        } elseif (! $skipMeterRange && $startMeter > 0) {
            $meter = (int) $fuelOdometer;

            if ($meter < (int) $startMeter) {
                $errors["expenses.{$index}.fuel_odometer_km"] = [
                    sprintf(
                        'Meter reading cannot be less than Trip Start Meter Reading (%s KM).',
                        $this->formatMeterKmLabel($startMeter),
                    ),
                ];
            } elseif ($endMeter !== null && $endMeter > 0 && $meter > (int) $endMeter) {
                $errors["expenses.{$index}.fuel_odometer_km"] = [
                    sprintf(
                        'Meter reading cannot be greater than Trip End Meter Reading (%s KM).',
                        $this->formatMeterKmLabel($endMeter),
                    ),
                ];
            }
        }

        if ($fuelLiters === null || $fuelLiters === '') {
            $errors["expenses.{$index}.fuel_liters"] = ['Fuel liters is required.'];
        } elseif (! is_numeric($fuelLiters) || (float) $fuelLiters <= 0) {
            $errors["expenses.{$index}.fuel_liters"] = ['Fuel liters must be greater than zero.'];
        } elseif (! $this->hasValidDecimalPlaces($fuelLiters, self::FUEL_DECIMAL_PLACES)) {
            $errors["expenses.{$index}.fuel_liters"] = [
                sprintf('Fuel liters must have at most %d decimal places.', self::FUEL_DECIMAL_PLACES),
            ];
        } elseif ((float) $fuelLiters > self::MAX_FUEL_LITERS) {
            $errors["expenses.{$index}.fuel_liters"] = [
                sprintf('Fuel liters cannot exceed %d L.', self::MAX_FUEL_LITERS),
            ];
        }

        if ($fuelRate === null || $fuelRate === '') {
            $errors["expenses.{$index}.fuel_rate"] = ['Diesel rate is required.'];
        } elseif (! is_numeric($fuelRate) || (float) $fuelRate <= 0) {
            $errors["expenses.{$index}.fuel_rate"] = ['Diesel rate must be greater than zero.'];
        } elseif (! $this->hasValidDecimalPlaces($fuelRate, self::FUEL_DECIMAL_PLACES)) {
            $errors["expenses.{$index}.fuel_rate"] = [
                sprintf('Diesel rate must have at most %d decimal places.', self::FUEL_DECIMAL_PLACES),
            ];
        } elseif ((float) $fuelRate > self::MAX_FUEL_RATE) {
            $errors["expenses.{$index}.fuel_rate"] = [
                sprintf('Diesel rate cannot exceed ₹%d per liter.', self::MAX_FUEL_RATE),
            ];
        }
    }

    private function formatMeterKmLabel(float $meter): string
    {
        return rtrim(rtrim(number_format($meter, 2, '.', ''), '0'), '.');
    }

    private function hasValidDecimalPlaces(mixed $value, int $maxPlaces): bool
    {
        $normalized = (string) $value;

        if (! str_contains($normalized, '.')) {
            return true;
        }

        [$whole, $fraction] = explode('.', $normalized, 2);
        unset($whole);

        return strlen(rtrim($fraction, '0')) <= $maxPlaces;
    }

    public function assertPositiveFreightTotal(array $routeLegs): void
    {
        $freightTotal = 0.0;

        foreach ($routeLegs as $leg) {
            $freightTotal += (float) ($leg['freight_amount'] ?? 0);
        }

        if ($freightTotal <= 0) {
            throw ValidationException::withMessages([
                'route_legs' => ['Total freight must be greater than zero before creating a trip.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assertStartMeterReadingIsPresent(array $data): void
    {
        $startMeter = (float) ($data['start_meter_reading_km'] ?? 0);

        if ($startMeter <= 0) {
            throw ValidationException::withMessages([
                'start_meter_reading_km' => ['Start meter reading is required and must be greater than zero.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assertOptionalEndMeterReadingOnCreate(array $data, string $vehicleId): void
    {
        $endMeterRaw = $data['end_meter_reading_km'] ?? null;

        if ($endMeterRaw === null || $endMeterRaw === '') {
            return;
        }

        $startMeter = (float) ($data['start_meter_reading_km'] ?? 0);
        $endMeter = (float) $endMeterRaw;

        if ($endMeter <= 0) {
            throw ValidationException::withMessages([
                'end_meter_reading_km' => ['End meter reading must be greater than zero.'],
            ]);
        }

        if ($endMeter < $startMeter) {
            throw ValidationException::withMessages([
                'end_meter_reading_km' => ['End Meter Reading cannot be less than Start Meter Reading.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function tripStatusValue(Trip $trip): string
    {
        return $trip->status instanceof TripStatus
            ? $trip->status->value
            : strtoupper((string) $trip->status);
    }

    public function assertStatusTransition(Trip $trip, string $newStatus, array $data = []): void
    {
        $current = $this->tripStatusValue($trip);

        $next = strtoupper($newStatus);

        if ($current === $next) {
            return;
        }

        $allowed = self::ALLOWED_STATUS_TRANSITIONS[$current] ?? [];

        if (! in_array($next, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot change trip status from {$current} to {$next}."],
            ]);
        }

        if ($next === TripStatus::Completed->value) {
            $startAt = $data['start_at'] ?? $trip->start_at;

            if ($startAt === null) {
                throw ValidationException::withMessages([
                    'start_at' => ['Trip start date is required before marking a trip as completed. Edit the trip and set the start date first.'],
                ]);
            }

            $endAt = $data['end_at'] ?? $trip->end_at;

            if ($endAt === null) {
                throw ValidationException::withMessages([
                    'end_at' => ['End date is required before marking a trip as completed.'],
                ]);
            }

            $actualArrivalAt = $data['actual_arrival_at'] ?? $trip->actual_arrival_at;

            if ($actualArrivalAt === null) {
                throw ValidationException::withMessages([
                    'actual_arrival_at' => ['Actual arrival date is required before marking a trip as completed.'],
                ]);
            }

            $this->assertMeterReadingsOnComplete($trip, $data);

            $this->assertCompletionDatesAreValid($trip, $data);
        }

        if ($next === TripStatus::Cancelled->value) {
            $startAt = $data['start_at'] ?? $trip->start_at;

            if ($startAt === null) {
                throw ValidationException::withMessages([
                    'start_at' => ['Trip start date is required before cancelling a trip. Edit the trip and set the start date first.'],
                ]);
            }

            $cancelledAt = $data['cancelled_at'] ?? $trip->cancelled_at;

            if ($cancelledAt === null) {
                throw ValidationException::withMessages([
                    'cancelled_at' => ['Cancellation date is required.'],
                ]);
            }

            $reason = trim((string) ($data['cancellation_reason'] ?? $trip->cancellation_reason ?? ''));

            if ($reason === '') {
                throw ValidationException::withMessages([
                    'cancellation_reason' => ['Please provide a reason for cancelling this trip.'],
                ]);
            }

            $this->assertMeterReadingsOnComplete($trip, $data, 'cancel');
        }
    }

    public function assertNoDuplicateTripWindow(
        string $vehicleId,
        ?string $startAt,
        ?string $endAt,
        ?string $ignoreTripId = null,
    ): void {
        $overlap = $this->findOverlappingOpenTrip(
            vehicleId: $vehicleId,
            startAt: $startAt,
            endAt: $endAt,
            ignoreTripId: $ignoreTripId,
        );

        if ($overlap !== null) {
            throw ValidationException::withMessages([
                'start_at' => ['This truck already has an open trip in the selected date range. Complete or cancel it first.'],
                'vehicle_id' => ["Trip {$overlap->trip_number} is already scheduled for this truck on overlapping dates."],
            ]);
        }
    }

    public function assertNoDuplicateDriverTripWindow(
        string $driverId,
        ?string $startAt,
        ?string $endAt,
        ?string $ignoreTripId = null,
    ): void {
        $overlap = $this->findOverlappingOpenTrip(
            driverId: $driverId,
            startAt: $startAt,
            endAt: $endAt,
            ignoreTripId: $ignoreTripId,
        );

        if ($overlap !== null) {
            throw ValidationException::withMessages([
                'start_at' => ['This driver already has an open trip in the selected date range. Complete or cancel it first.'],
                'driver_id' => ["Trip {$overlap->trip_number} is already scheduled for this driver on overlapping dates."],
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveTripWindow(?string $startAt, ?string $endAt): array
    {
        $windowStart = Carbon::parse($startAt)->toDateString();
        $windowEnd = $endAt !== null
            ? Carbon::parse($endAt)->toDateString()
            : $windowStart;

        return [$windowStart, $windowEnd];
    }

    public function resolvePrimaryDriverIdForVehicle(string $vehicleId, ?string $asOfDate = null): ?string
    {
        $date = $asOfDate !== null
            ? Carbon::parse($asOfDate)->toDateString()
            : now()->toDateString();

        $assignment = DriverAssignment::query()
            ->where('vehicle_id', $vehicleId)
            ->activePrimaryOn($date)
            ->latest('assigned_from')
            ->first();

        return $assignment?->driver_id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * @param  array<string, mixed>  $data
     */
    public function assertCompletionDatesAreValid(Trip $trip, array $data = []): void
    {
        $startAt = $data['start_at'] ?? $trip->start_at;
        $endAt = $data['end_at'] ?? $trip->end_at;
        $actualArrivalAt = $data['actual_arrival_at'] ?? $trip->actual_arrival_at;

        if ($startAt === null || $endAt === null || $actualArrivalAt === null) {
            throw ValidationException::withMessages([
                'trip' => ['Trip start, end, and actual arrival dates are required to validate completion.'],
            ]);
        }

        $start = Carbon::parse($startAt)->toDateString();
        $end = Carbon::parse($endAt)->toDateString();
        $arrival = Carbon::parse($actualArrivalAt)->toDateString();

        if ($arrival < $start) {
            throw ValidationException::withMessages([
                'actual_arrival_at' => ['Actual arrival cannot be before the trip start date.'],
            ]);
        }

        if ($end < $arrival) {
            throw ValidationException::withMessages([
                'end_at' => ['End date cannot be before the actual arrival date.'],
            ]);
        }

        if ($end < $start) {
            throw ValidationException::withMessages([
                'end_at' => ['End date cannot be before the trip start date.'],
            ]);
        }
    }

    public function assertMeterReadingsOnComplete(Trip $trip, array $data = [], string $action = 'complete'): void
    {
        $actionLabel = $action === 'cancel' ? 'cancel' : 'complete';
        $startMeter = array_key_exists('start_meter_reading_km', $data)
            ? (float) $data['start_meter_reading_km']
            : (float) ($trip->start_meter_reading_km ?? 0);
        $endMeterRaw = $data['end_meter_reading_km'] ?? $trip->end_meter_reading_km;

        if ($startMeter <= 0) {
            throw ValidationException::withMessages([
                'start_meter_reading_km' => ['Start meter reading is required and must be greater than zero.'],
            ]);
        }

        if ($endMeterRaw === null || $endMeterRaw === '') {
            throw ValidationException::withMessages([
                'end_meter_reading_km' => ["End meter reading is required to {$actionLabel} the trip."],
            ]);
        }

        $endMeter = (float) $endMeterRaw;

        if ($endMeter <= 0) {
            throw ValidationException::withMessages([
                'end_meter_reading_km' => ['End meter reading must be greater than zero.'],
            ]);
        }

        if ($endMeter < $startMeter) {
            throw ValidationException::withMessages([
                'end_meter_reading_km' => ['End Meter Reading cannot be less than Start Meter Reading.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assertMeterReadingsOnUpdate(Trip $trip, array $data = []): void
    {
        $startMeter = array_key_exists('start_meter_reading_km', $data)
            ? (float) $data['start_meter_reading_km']
            : (float) ($trip->start_meter_reading_km ?? 0);

        if ($startMeter <= 0) {
            throw ValidationException::withMessages([
                'start_meter_reading_km' => ['Start meter reading is required and must be greater than zero.'],
            ]);
        }

        $endMeterRaw = array_key_exists('end_meter_reading_km', $data)
            ? $data['end_meter_reading_km']
            : $trip->end_meter_reading_km;

        if ($endMeterRaw === null || $endMeterRaw === '') {
            return;
        }

        $endMeter = (float) $endMeterRaw;

        if ($endMeter <= 0) {
            throw ValidationException::withMessages([
                'end_meter_reading_km' => ['End meter reading must be greater than zero.'],
            ]);
        }

        if ($endMeter < $startMeter) {
            throw ValidationException::withMessages([
                'end_meter_reading_km' => ['End Meter Reading cannot be less than Start Meter Reading.'],
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $newRouteLegs
     */
    public function assertRouteLegsCanBeUpdated(Trip $trip, array $newRouteLegs): void
    {
        $trip->load(['routeLegs']);

        $newPairKeys = collect($newRouteLegs)
            ->map(fn (array $leg): string => $this->routeLegPairKey($leg))
            ->flip()
            ->all();

        foreach ($trip->routeLegs as $oldLeg) {
            $pairKey = $this->routeLegPairKeyFromLeg($oldLeg);

            if (isset($newPairKeys[$pairKey])) {
                continue;
            }

            $expenseTotal = (float) TripExpense::query()
                ->where('trip_route_leg_id', $oldLeg->id)
                ->sum('amount');

            if ($expenseTotal <= 0) {
                continue;
            }

            $from = trim((string) ($oldLeg->from_city ?? $oldLeg->from_location ?? '—'));
            $to = trim((string) ($oldLeg->to_city ?? $oldLeg->to_location ?? '—'));

            throw ValidationException::withMessages([
                'route_legs' => [
                    sprintf(
                        'Stop %d (%s → %s) has expenses. Clear them on Trip Expenses before changing the route.',
                        (int) $oldLeg->sequence_no,
                        $from !== '' ? $from : '—',
                        $to !== '' ? $to : '—',
                    ),
                ],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $leg
     */
    private function routeLegPairKey(array $leg): string
    {
        return ((int) ($leg['from_station_id'] ?? 0)).':'.((int) ($leg['to_station_id'] ?? 0));
    }

    private function routeLegPairKeyFromLeg(\App\TripRouteLeg $leg): string
    {
        return ((int) ($leg->from_station_id ?? 0)).':'.((int) ($leg->to_station_id ?? 0));
    }
}
