<?php

namespace App\Modules\DriverManagement\Services;

use App\AttendanceMonthLock;
use App\Enums\AttendanceMonthStatus;
use App\Enums\WagePaymentStatus;
use App\MonthlyDriverWage;
use App\Support\CurrentTenant;
use App\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AttendanceMonthLockService
{
    public function statusForMonth(string $month): AttendanceMonthStatus
    {
        return $this->findForMonth($month)?->status ?? AttendanceMonthStatus::Open;
    }

    /**
     * @return array{status: string, closed_at: string|null, locked_at: string|null}
     */
    public function summaryForMonth(string $month): array
    {
        $lock = $this->findForMonth($month);
        $status = $lock?->status ?? AttendanceMonthStatus::Open;

        return [
            'status' => $status->value,
            'closed_at' => $lock?->closed_at?->toISOString(),
            'locked_at' => $lock?->locked_at?->toISOString(),
        ];
    }

    public function assertEditableForDate(string $attendanceDate): void
    {
        if ($this->statusForMonth($this->monthKeyFromDate($attendanceDate)) !== AttendanceMonthStatus::Open) {
            throw ValidationException::withMessages([
                'attendance_date' => ['This month is closed or locked. Exceptions cannot be added or changed.'],
            ]);
        }
    }

    public function isMonthOpen(string $month): bool
    {
        return $this->statusForMonth($month) === AttendanceMonthStatus::Open;
    }

    public function periodHasNonOpenMonth(string $from, ?string $to = null): bool
    {
        return $this->lockedMonthsInPeriod($from, $to) !== [];
    }

    /**
     * @return list<array{month: string, label: string, status: string}>
     */
    public function lockedMonthsInPeriod(string $from, ?string $to = null): array
    {
        $locked = [];

        foreach ($this->monthsInPeriod($from, $to) as $month) {
            $status = $this->statusForMonth($month);

            if ($status === AttendanceMonthStatus::Open) {
                continue;
            }

            $locked[] = [
                'month' => $month,
                'label' => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
                'status' => $status->value,
            ];
        }

        return $locked;
    }

    public function formatLockedMonthsList(string $from, ?string $to = null): string
    {
        return collect($this->lockedMonthsInPeriod($from, $to))
            ->map(function (array $month): string {
                $statusLabel = $month['status'] === AttendanceMonthStatus::Paid->value ? 'Locked' : 'Closed';

                return "{$month['label']} ({$statusLabel})";
            })
            ->join(', ');
    }

    public function assertPeriodIsOpen(string $from, ?string $to = null, ?string $truckLabel = null): void
    {
        $lockedMonths = $this->lockedMonthsInPeriod($from, $to);

        if ($lockedMonths === []) {
            return;
        }

        $periodLabel = $this->formatDateRangeLabel($from, $to);
        $monthsLabel = $this->formatLockedMonthsList($from, $to);
        $truckPrefix = $truckLabel !== null ? "Truck {$truckLabel}: " : '';

        throw ValidationException::withMessages([
            'assigned_from' => [
                "{$truckPrefix}Assignment period {$periodLabel} overlaps closed/locked payroll month(s): {$monthsLabel}.",
            ],
        ]);
    }

    private function formatDateRangeLabel(string $from, ?string $to): string
    {
        $fromLabel = Carbon::parse($from)->format('d M, Y');
        $toLabel = $to !== null ? Carbon::parse($to)->format('d M, Y') : 'Current';

        return "{$fromLabel} to {$toLabel}";
    }

    /**
     * @return list<string>
     */
    private function monthsInPeriod(string $from, ?string $to): array
    {
        $start = Carbon::parse($from)->startOfMonth();
        $end = Carbon::parse($to ?? Carbon::today()->toDateString())->endOfMonth();
        $months = [];

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addMonth()) {
            $months[] = $cursor->format('Y-m');
        }

        return $months;
    }

    public function canCloseMonth(string $month): bool
    {
        [$monthStart] = $this->monthBoundaries($month);

        return Carbon::parse($monthStart)->lt($this->currentMonthStartInTenantTimezone());
    }

    public function assertMonthCanBeClosed(string $month): void
    {
        if ($this->canCloseMonth($month)) {
            return;
        }

        throw ValidationException::withMessages([
            'month' => ['Only completed past months can be locked. The current month and future months cannot be locked.'],
        ]);
    }

    public function closeMonth(string $month, ?string $actorId = null): AttendanceMonthLock
    {
        [$monthStart] = $this->monthBoundaries($month);

        $lock = $this->findForMonth($month) ?? new AttendanceMonthLock([
            'tenant_id' => CurrentTenant::requireId(),
            'month' => $monthStart,
        ]);

        if ($lock->exists && $lock->status === AttendanceMonthStatus::Paid) {
            throw ValidationException::withMessages([
                'month' => ['This month is locked after payment and cannot be closed again.'],
            ]);
        }

        $lock->fill([
            'status' => AttendanceMonthStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $actorId,
        ]);

        $lock->save();

        return $lock;
    }

    public function lockMonth(string $month, ?string $actorId = null): AttendanceMonthLock
    {
        $lock = $this->findForMonth($month);

        if ($lock === null || $lock->status === AttendanceMonthStatus::Open) {
            throw ValidationException::withMessages([
                'month' => ['Close the month first before locking payments.'],
            ]);
        }

        if ($lock->status === AttendanceMonthStatus::Paid) {
            throw ValidationException::withMessages([
                'month' => ['This month is already locked.'],
            ]);
        }

        $lock->fill([
            'status' => AttendanceMonthStatus::Paid,
            'locked_at' => now(),
            'locked_by' => $actorId,
        ]);

        $lock->save();

        return $lock;
    }

    public function reopenMonth(string $month): AttendanceMonthLock
    {
        [$monthStart] = $this->monthBoundaries($month);

        $lock = AttendanceMonthLock::query()
            ->whereDate('month', $monthStart)
            ->first();

        if ($lock === null || $lock->status === AttendanceMonthStatus::Open) {
            throw ValidationException::withMessages([
                'month' => ['This month is not closed.'],
            ]);
        }

        if ($lock->status === AttendanceMonthStatus::Paid) {
            throw ValidationException::withMessages([
                'month' => ['This month is finalized after payment and cannot be reopened.'],
            ]);
        }

        MonthlyDriverWage::query()
            ->where('tenant_id', CurrentTenant::requireId())
            ->whereDate('month', $monthStart)
            ->where('payment_status', WagePaymentStatus::Unpaid->value)
            ->delete();

        $lock->fill([
            'status' => AttendanceMonthStatus::Open,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        $lock->save();

        return $lock;
    }

    public function unlockMonth(string $month): AttendanceMonthLock
    {
        $lock = $this->findForMonth($month);

        if ($lock === null || $lock->status === AttendanceMonthStatus::Open) {
            throw ValidationException::withMessages([
                'month' => ['This month is not locked.'],
            ]);
        }

        if ($lock->status === AttendanceMonthStatus::Paid) {
            throw ValidationException::withMessages([
                'month' => ['This month is finalized after payment and cannot be unlocked.'],
            ]);
        }

        return $this->reopenMonth($month);
    }

    private function findForMonth(string $month): ?AttendanceMonthLock
    {
        [$monthStart] = $this->monthBoundaries($month);

        return AttendanceMonthLock::query()
            ->whereDate('month', $monthStart)
            ->first();
    }

    private function monthKeyFromDate(string $date): string
    {
        return Carbon::parse($date)->format('Y-m');
    }

    /**
     * @return array{0: string}
     */
    private function monthBoundaries(string $month): array
    {
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        return [$monthStart->toDateString()];
    }

    private function currentMonthStartInTenantTimezone(): Carbon
    {
        $tenant = Tenant::query()->find(CurrentTenant::requireId());
        $timezone = $tenant?->timezone ?? config('app.timezone', 'UTC');

        return Carbon::now($timezone)->startOfMonth();
    }
}
