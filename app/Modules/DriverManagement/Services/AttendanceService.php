<?php

namespace App\Modules\DriverManagement\Services;

use App\Attendance;
use App\AttendanceMonthLock;
use App\Driver;
use App\DriverAssignment;
use App\Enums\AttendanceMarkedVia;
use App\Enums\AttendanceMonthStatus;
use App\Enums\AttendanceStatus;
use App\Enums\WagePaymentStatus;
use App\Modules\DriverManagement\Repositories\AttendanceRepository;
use App\MonthlyDriverWage;
use App\Support\CurrentTenant;
use App\Support\MonthlyWageAccrual;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(
        private readonly AttendanceRepository $attendanceRepository,
        private readonly AttendanceCalendarService $attendanceCalendar,
        private readonly AttendanceMonthLockService $monthLockService,
        private readonly MonthlySettlementService $monthlySettlementService,
        private readonly SubstituteDriverAvailability $substituteDriverAvailability,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->attendanceRepository->paginate($perPage, $filters);
    }

    /**
     * @return array{summary: array{marked: int, present: int, absent: int, paid: int, unpaid: int}, rows: list<array<string, mixed>>}
     */
    public function monthOverview(string $fromDate, string $toDate): array
    {
        $month = Carbon::parse($fromDate)->format('Y-m');
        [$monthStart] = $this->monthBoundaries($month);

        $paidDriverIds = MonthlyDriverWage::query()
            ->whereDate('month', $monthStart)
            ->where('payment_status', WagePaymentStatus::Paid->value)
            ->pluck('driver_id')
            ->all();

        $overview = $this->attendanceCalendar->buildOverview($fromDate, $toDate, $paidDriverIds);
        $overview['month_lock'] = $this->monthLockService->summaryForMonth($month);
        $overview['summary']['marked'] = $overview['summary']['exceptions'];

        return $overview;
    }

    /**
     * @return Collection<int, MonthlyDriverWage>
     */
    public function listMonthlyWages(string $month): Collection
    {
        [$monthStart] = $this->monthBoundaries($month);

        return MonthlyDriverWage::query()
            ->whereDate('month', $monthStart)
            ->with('driver')
            ->orderByDesc('total_wage')
            ->get();
    }

    /**
     * @return Collection<int, MonthlyDriverWage>
     */
    public function closeMonth(string $month, ?string $actorId = null): Collection
    {
        return DB::transaction(function () use ($month, $actorId): Collection {
            [$monthStart, $monthEnd] = $this->monthBoundaries($month);
            $tenantId = CurrentTenant::requireId();

            AttendanceMonthLock::query()
                ->where('tenant_id', $tenantId)
                ->whereDate('month', $monthStart)
                ->lockForUpdate()
                ->first();

            $driverStats = $this->attendanceCalendar->driverPrimaryDayStats($monthStart, $monthEnd);
            $substituteEarnings = $this->attendanceCalendar->driverSubstituteEarnings($monthStart, $monthEnd);
            $calendarDays = (int) Carbon::parse($monthStart)->daysInMonth;
            $driverIds = collect(array_keys($driverStats))
                ->merge(array_keys($substituteEarnings))
                ->unique()
                ->values();
            $activeDriverIds = [];

            foreach ($driverIds as $driverId) {
                $stats = $driverStats[$driverId] ?? ['present' => 0, 'absent' => 0, 'leave' => 0, 'payable_days' => 0];
                $substitute = $substituteEarnings[$driverId] ?? ['days' => 0, 'total_wage' => 0.0];

                if (((int) $stats['present'] + (int) $stats['absent'] + (int) $stats['leave']) === 0 && $substitute['days'] === 0) {
                    continue;
                }

                $activeDriverIds[] = $driverId;

                $monthlyWage = MonthlyDriverWage::query()->firstOrNew([
                    'tenant_id' => $tenantId,
                    'driver_id' => $driverId,
                    'month' => $monthStart,
                ]);

                $isPaid = $monthlyWage->exists && $monthlyWage->payment_status === WagePaymentStatus::Paid;

                if (! $monthlyWage->exists) {
                    $monthlyWage->created_by = $actorId;
                }

                if (! $isPaid) {
                    $monthlyWage->payment_status = WagePaymentStatus::Unpaid->value;
                    $monthlyWage->paid_at = null;
                }

                $monthlyWage->closed_at = now();
                $monthlyWage->updated_by = $actorId;

                $driver = Driver::query()->find($driverId);

                if ($driver !== null) {
                    $this->monthlySettlementService->applySettlementDefaults(
                        $monthlyWage,
                        $driver,
                        $stats,
                        $calendarDays,
                        $substitute,
                    );
                }

                $monthlyWage->save();
            }

            MonthlyDriverWage::query()
                ->whereDate('month', $monthStart)
                ->where('payment_status', WagePaymentStatus::Unpaid->value)
                ->when(
                    count($activeDriverIds) > 0,
                    fn ($query) => $query->whereNotIn('driver_id', $activeDriverIds),
                )
                ->delete();

            $this->monthLockService->closeMonth($month, $actorId);

            return $this->listMonthlyWages($month);
        });
    }

    public function lockMonth(string $month, ?string $actorId = null): AttendanceMonthLock
    {
        return $this->monthLockService->lockMonth($month, $actorId);
    }

    public function reopenMonth(string $month): AttendanceMonthLock
    {
        return $this->monthLockService->reopenMonth($month);
    }

    public function unlockMonth(string $month): AttendanceMonthLock
    {
        return $this->monthLockService->unlockMonth($month);
    }

    /**
     * @return Collection<int, MonthlyDriverWage>
     */
    public function markAllMonthlyWagesPaid(string $month, ?string $actorId = null, ?string $paymentNotes = null): Collection
    {
        return DB::transaction(function () use ($month, $actorId, $paymentNotes): Collection {
            [$monthStart] = $this->monthBoundaries($month);
            $tenantId = CurrentTenant::requireId();
            $status = $this->monthLockService->statusForMonth($month);

            if ($status === AttendanceMonthStatus::Open) {
                throw ValidationException::withMessages([
                    'month' => ['Close the month before marking monthly payments.'],
                ]);
            }

            AttendanceMonthLock::query()
                ->where('tenant_id', $tenantId)
                ->whereDate('month', $monthStart)
                ->lockForUpdate()
                ->first();

            $unpaid = MonthlyDriverWage::query()
                ->where('tenant_id', $tenantId)
                ->whereDate('month', $monthStart)
                ->where('payment_status', WagePaymentStatus::Unpaid->value)
                ->lockForUpdate()
                ->get();

            foreach ($unpaid as $monthlyDriverWage) {
                if ((float) $monthlyDriverWage->total_wage > 0) {
                    $this->markMonthlyWagePaid($monthlyDriverWage, [
                        'payment_notes' => $paymentNotes,
                        'updated_by' => $actorId,
                    ]);

                    continue;
                }

                $monthlyDriverWage->update([
                    'payment_status' => WagePaymentStatus::Paid,
                    'paid_at' => now(),
                    'payment_notes' => $paymentNotes,
                    'updated_by' => $actorId,
                ]);
            }

            return $this->listMonthlyWages($month);
        });
    }

    public function mark(array $payload): Attendance
    {
        $this->monthLockService->assertEditableForDate((string) $payload['attendance_date']);

        $existing = Attendance::query()
            ->where('tenant_id', CurrentTenant::requireId())
            ->where('vehicle_id', $payload['vehicle_id'])
            ->whereDate('attendance_date', $payload['attendance_date'])
            ->first();

        $payload = $this->preparePayload($payload, $existing);

        return $this->attendanceRepository->upsertByVehicleAndDate(
            [
                'tenant_id' => CurrentTenant::requireId(),
                'vehicle_id' => $payload['vehicle_id'],
                'attendance_date' => $payload['attendance_date'],
            ],
            $payload
        );
    }

    public function update(Attendance $attendance, array $payload): Attendance
    {
        $this->monthLockService->assertEditableForDate(
            (string) ($payload['attendance_date'] ?? $attendance->attendance_date->toDateString()),
        );

        $payload = $this->preparePayload($payload, $attendance);

        return $this->attendanceRepository->update($attendance, $payload);
    }

    public function markWagePaid(Attendance $attendance, array $payload): Attendance
    {
        if ((float) $attendance->wage_amount <= 0) {
            throw ValidationException::withMessages([
                'wage_amount' => ['No wage is due for this attendance record.'],
            ]);
        }

        if ($attendance->isWagePaid()) {
            throw ValidationException::withMessages([
                'wage_payment_status' => ['Wage is already marked as paid.'],
            ]);
        }

        return $this->attendanceRepository->update($attendance, [
            'wage_payment_status' => WagePaymentStatus::Paid,
            'wage_paid_at' => now(),
            'wage_transfer_notes' => $payload['wage_transfer_notes'] ?? $attendance->wage_transfer_notes,
            'updated_by' => $payload['updated_by'] ?? null,
        ]);
    }

    public function markMonthlyWagePaid(MonthlyDriverWage $monthlyDriverWage, array $payload): MonthlyDriverWage
    {
        if ((float) $monthlyDriverWage->total_wage <= 0) {
            throw ValidationException::withMessages([
                'total_wage' => ['No monthly wage is due for this record.'],
            ]);
        }

        if ($monthlyDriverWage->payment_status === WagePaymentStatus::Paid) {
            throw ValidationException::withMessages([
                'payment_status' => ['Monthly wage is already marked as paid.'],
            ]);
        }

        $monthlyWage = $monthlyDriverWage->month;
        $monthStart = Carbon::parse($monthlyWage)->startOfMonth()->toDateString();
        $monthEnd = Carbon::parse($monthlyWage)->endOfMonth()->toDateString();

        $notes = $payload['payment_notes'] ?? $monthlyDriverWage->payment_notes;

        Attendance::query()
            ->where('wage_owner_driver_id', $monthlyDriverWage->driver_id)
            ->whereDate('attendance_date', '>=', $monthStart)
            ->whereDate('attendance_date', '<=', $monthEnd)
            ->where('wage_amount', '>', 0)
            ->where('wage_payment_status', WagePaymentStatus::Unpaid->value)
            ->update([
                'wage_payment_status' => WagePaymentStatus::Paid->value,
                'wage_paid_at' => now(),
                'wage_transfer_notes' => $notes,
                'updated_by' => $payload['updated_by'] ?? null,
            ]);

        $monthlyDriverWage->update([
            'payment_status' => WagePaymentStatus::Paid,
            'paid_at' => now(),
            'payment_notes' => $notes,
            'updated_by' => $payload['updated_by'] ?? null,
        ]);

        return $monthlyDriverWage->fresh('driver');
    }

    public function delete(Attendance $attendance): void
    {
        $this->monthLockService->assertEditableForDate($attendance->attendance_date->toDateString());

        $this->attendanceRepository->delete($attendance);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function preparePayload(array $payload, ?Attendance $existing = null): array
    {
        $status = AttendanceStatus::from(strtoupper((string) $payload['status']));

        if ($status === AttendanceStatus::Present) {
            throw ValidationException::withMessages([
                'status' => ['Working days are automatic from driver assignment. Only mark absences, substitutes, or no-work days.'],
            ]);
        }

        $primaryDriverId = $payload['primary_driver_id'] ?? null;

        if ($primaryDriverId === null || $primaryDriverId === '') {
            $primaryDriverId = $this->resolvePrimaryDriverIdFromAssignment(
                $payload['vehicle_id'],
                (string) $payload['attendance_date'],
            );
        }

        if ($primaryDriverId === null) {
            throw ValidationException::withMessages([
                'primary_driver_id' => ['Primary driver is required or an active primary assignment must exist for the selected date.'],
            ]);
        }

        /** @var Driver $primaryDriver */
        $primaryDriver = Driver::query()->findOrFail($primaryDriverId);

        $temporaryDriverId = $payload['temporary_driver_id'] ?? null;
        $temporaryRemarks = $payload['temporary_assignment_remarks'] ?? null;

        if ($status === AttendanceStatus::Absent && $temporaryDriverId) {
            $this->substituteDriverAvailability->assertAvailable(
                (string) $payload['attendance_date'],
                (string) $temporaryDriverId,
                $existing?->id,
            );
        }

        $wageOwnerDriver = $this->resolveWageOwnerDriver($status, $primaryDriver, [
            ...$payload,
            'temporary_driver_id' => $temporaryDriverId,
        ]);

        $payload['status'] = $status->value;
        $payload['primary_driver_id'] = $primaryDriver->id;
        $payload['wage_owner_driver_id'] = $wageOwnerDriver->id;

        if ($status === AttendanceStatus::NoWorkDay) {
            $payload['temporary_driver_id'] = null;
            $payload['temporary_assignment_remarks'] = null;
        } else {
            $payload['temporary_driver_id'] = $temporaryDriverId ?: null;
            $payload['temporary_assignment_remarks'] = $temporaryRemarks;
        }

        if (! isset($payload['marked_via'])) {
            $payload['marked_via'] = AttendanceMarkedVia::AdminPanel->value;
        }

        if (! array_key_exists('wage_amount', $payload) || $payload['wage_amount'] === null) {
            $payload['wage_amount'] = $this->resolveWageAmount($status, $wageOwnerDriver, $payload);
        }

        if ($existing === null) {
            $payload['wage_payment_status'] = WagePaymentStatus::Unpaid->value;
        } elseif (! $existing->isWagePaid()) {
            $payload['wage_payment_status'] = WagePaymentStatus::Unpaid->value;
        } else {
            unset($payload['wage_payment_status']);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveWageAmount(AttendanceStatus $status, Driver $wageOwnerDriver, array $payload): float
    {
        $attendanceDate = (string) ($payload['attendance_date'] ?? now()->toDateString());
        $monthlyBase = (float) $wageOwnerDriver->daily_wage;

        if ($status === AttendanceStatus::Present) {
            return MonthlyWageAccrual::perDay($monthlyBase, $attendanceDate);
        }

        if ($status === AttendanceStatus::Absent && ! empty($payload['temporary_driver_id'])) {
            return MonthlyWageAccrual::perDay($monthlyBase, $attendanceDate);
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveWageOwnerDriver(AttendanceStatus $status, Driver $primaryDriver, array $payload): Driver
    {
        if ($status === AttendanceStatus::Absent && ! empty($payload['temporary_driver_id'])) {
            /** @var Driver */
            return Driver::query()->findOrFail($payload['temporary_driver_id']);
        }

        return $primaryDriver;
    }

    private function resolvePrimaryDriverIdFromAssignment(string $vehicleId, string $attendanceDate): ?string
    {
        $date = Carbon::parse($attendanceDate)->toDateString();

        return DriverAssignment::query()
            ->where('driver_assignments.tenant_id', CurrentTenant::requireId())
            ->where('driver_assignments.vehicle_id', $vehicleId)
            ->where('driver_assignments.is_primary', true)
            ->whereDate('driver_assignments.assigned_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('driver_assignments.assigned_to')
                    ->orWhereDate('driver_assignments.assigned_to', '>=', $date);
            })
            ->join('drivers', 'drivers.id', '=', 'driver_assignments.driver_id')
            ->where('drivers.is_active', true)
            ->value('driver_assignments.driver_id');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function monthBoundaries(string $month): array
    {
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        return [$monthStart->toDateString(), $monthStart->copy()->endOfMonth()->toDateString()];
    }
}
