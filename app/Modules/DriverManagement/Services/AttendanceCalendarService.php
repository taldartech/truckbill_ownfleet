<?php

namespace App\Modules\DriverManagement\Services;

use App\Attendance;
use App\Driver;
use App\DriverAssignment;
use App\Enums\AttendanceMarkedVia;
use App\Enums\AttendanceStatus;
use App\Support\CurrentTenant;
use App\Support\MonthlyWageAccrual;
use App\Support\PayrollPayDays;
use App\Tenant;
use App\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceCalendarService
{
    /**
     * @param  list<string>  $paidDriverIds
     * @return array{summary: array{exceptions: int, present: int, absent: int, no_work: int, paid: int, unpaid: int}, rows: list<array<string, mixed>>}
     */
    public function buildOverview(string $fromDate, string $toDate, array $paidDriverIds = []): array
    {
        $exceptions = $this->loadExceptions($fromDate, $toDate);
        $assignments = $this->loadAssignments($fromDate, $toDate);

        $vehicleIds = $assignments->pluck('vehicle_id')
            ->merge($exceptions->pluck('vehicle_id'))
            ->unique()
            ->values();

        $vehicles = Vehicle::query()
            ->whereIn('id', $vehicleIds)
            ->get(['id', 'registration_number'])
            ->keyBy('id');

        /** @var array<string, array<string, mixed>> $grouped */
        $grouped = [];

        $summary = [
            'exceptions' => $exceptions->count(),
            'present' => 0,
            'absent' => 0,
            'no_work' => 0,
            'paid' => 0,
            'unpaid' => 0,
        ];

        foreach ($this->dateRange($fromDate, $toDate) as $date) {
            foreach ($vehicleIds as $vehicleId) {
                $assignment = $this->assignmentForDate($assignments, (string) $vehicleId, $date);
                $exception = $exceptions->get($vehicleId.'|'.$date);

                if ($assignment === null && $exception === null) {
                    continue;
                }

                $cell = $this->resolveCell($date, $assignment, $exception, $paidDriverIds);

                if ($cell === null) {
                    continue;
                }

                $this->addCellToGrouped($grouped, $vehicles, (string) $vehicleId, $date, $cell);
                $this->incrementSummary($summary, $cell);
            }
        }

        $referenceDate = $this->referenceDateForDriverLabel($fromDate, $toDate);

        foreach ($grouped as $vehicleId => &$row) {
            $assignment = $this->assignmentForDate($assignments, (string) $vehicleId, $referenceDate);
            $row['driver_name'] = $assignment?->driver?->name ?? '—';
        }
        unset($row);

        $rows = array_values($grouped);

        usort($rows, fn (array $left, array $right): int => strcmp($left['vehicle_label'], $right['vehicle_label']));

        return [
            'summary' => $summary,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, array{payable_days: int, total_wage: float}>
     */
    public function driverWageTotals(string $fromDate, string $toDate): array
    {
        $overview = $this->buildOverview($fromDate, $toDate);
        /** @var array<string, array{payable_days: int, total_wage: float}> $totals */
        $totals = [];

        /** @var array<string, bool> $counted */
        $counted = [];

        foreach ($overview['rows'] as $row) {
            foreach ($row['days'] as $date => $cell) {
                if (! $this->isPayableCell($cell)) {
                    continue;
                }

                $driverId = (string) ($cell['wage_owner_driver_id'] ?? $cell['primary_driver_id'] ?? '');

                if ($driverId === '') {
                    continue;
                }

                $key = $driverId.'|'.$date;

                if (isset($counted[$key])) {
                    continue;
                }

                $counted[$key] = true;

                if (! isset($totals[$driverId])) {
                    $totals[$driverId] = ['payable_days' => 0, 'total_wage' => 0.0];
                }

                $totals[$driverId]['payable_days']++;
                $totals[$driverId]['total_wage'] += (float) $cell['wage_amount'];
            }
        }

        foreach ($totals as $driverId => $values) {
            $totals[$driverId]['total_wage'] = round($values['total_wage'], 2);
        }

        return $totals;
    }

    /**
     * @return array<string, array{present: int, absent: int, leave: int, payable_days: int}>
     */
    public function driverPrimaryDayStats(string $fromDate, string $toDate): array
    {
        $overview = $this->buildOverview($fromDate, $toDate);
        /** @var array<string, array<string, mixed>> $perDriverDate */
        $perDriverDate = [];

        foreach ($overview['rows'] as $row) {
            foreach ($row['days'] as $date => $cell) {
                $this->rememberDriverDay(
                    $perDriverDate,
                    (string) $date,
                    $cell,
                    (string) ($cell['primary_driver_id'] ?? ''),
                );
            }
        }

        return $this->aggregateDriverDayStats($perDriverDate);
    }

    /**
     * @return array<string, array{days: int, total_wage: float}>
     */
    public function driverSubstituteEarnings(string $fromDate, string $toDate): array
    {
        $overview = $this->buildOverview($fromDate, $toDate);
        /** @var array<string, array{days: int, total_wage: float}> $earnings */
        $earnings = [];
        /** @var array<string, bool> $counted */
        $counted = [];
        /** @var array<string, bool> $substituteIds */
        $substituteIds = [];

        foreach ($overview['rows'] as $row) {
            foreach ($row['days'] as $cell) {
                $substituteId = (string) ($cell['temporary_driver_id'] ?? '');

                if ($substituteId !== '') {
                    $substituteIds[$substituteId] = true;
                }
            }
        }

        /** @var array<string, float> $substituteBases */
        $substituteBases = Driver::query()
            ->whereIn('id', array_keys($substituteIds))
            ->pluck('daily_wage', 'id')
            ->map(fn ($base): float => (float) $base)
            ->all();

        foreach ($overview['rows'] as $row) {
            foreach ($row['days'] as $date => $cell) {
                $substituteId = (string) ($cell['temporary_driver_id'] ?? '');

                if ($substituteId === '') {
                    continue;
                }

                $key = $substituteId.'|'.$date;

                if (isset($counted[$key])) {
                    continue;
                }

                $counted[$key] = true;
                $pay = $this->substituteWageForCell(
                    $cell,
                    (string) $date,
                    $substituteBases[$substituteId] ?? 0.0,
                );

                if (! isset($earnings[$substituteId])) {
                    $earnings[$substituteId] = ['days' => 0, 'total_wage' => 0.0];
                }

                $earnings[$substituteId]['days']++;
                $earnings[$substituteId]['total_wage'] += $pay;
            }
        }

        foreach ($earnings as $driverId => $values) {
            $earnings[$driverId]['total_wage'] = round($values['total_wage'], 2);
        }

        return $earnings;
    }

    /**
     * Days and reference amounts when a substitute worked on the primary driver's truck.
     * Shown on the payroll register for reference only — not deducted from primary net pay.
     *
     * @return array<string, array{days: int, total_wage: float}>
     */
    public function primarySubstituteLiabilities(string $fromDate, string $toDate): array
    {
        $overview = $this->buildOverview($fromDate, $toDate);
        /** @var array<string, array{days: int, total_wage: float}> $liabilities */
        $liabilities = [];
        /** @var array<string, bool> $counted */
        $counted = [];
        /** @var array<string, bool> $substituteIds */
        $substituteIds = [];

        foreach ($overview['rows'] as $row) {
            foreach ($row['days'] as $cell) {
                $substituteId = (string) ($cell['temporary_driver_id'] ?? '');

                if ($substituteId !== '') {
                    $substituteIds[$substituteId] = true;
                }
            }
        }

        /** @var array<string, float> $substituteBases */
        $substituteBases = Driver::query()
            ->whereIn('id', array_keys($substituteIds))
            ->pluck('daily_wage', 'id')
            ->map(fn ($base): float => (float) $base)
            ->all();

        foreach ($overview['rows'] as $row) {
            foreach ($row['days'] as $date => $cell) {
                $substituteId = (string) ($cell['temporary_driver_id'] ?? '');

                if ($substituteId === '') {
                    continue;
                }

                $ownerId = (string) ($cell['wage_owner_driver_id'] ?? $cell['primary_driver_id'] ?? '');

                if ($ownerId === '') {
                    continue;
                }

                $key = $ownerId.'|'.$date;

                if (isset($counted[$key])) {
                    continue;
                }

                $counted[$key] = true;
                $pay = $this->substituteWageForCell(
                    $cell,
                    (string) $date,
                    $substituteBases[$substituteId] ?? 0.0,
                );

                if (! isset($liabilities[$ownerId])) {
                    $liabilities[$ownerId] = ['days' => 0, 'total_wage' => 0.0];
                }

                $liabilities[$ownerId]['days']++;
                $liabilities[$ownerId]['total_wage'] += $pay;
            }
        }

        foreach ($liabilities as $driverId => $values) {
            $liabilities[$driverId]['total_wage'] = round($values['total_wage'], 2);
        }

        return $liabilities;
    }

    /**
     * @param  array<string, mixed>  $cell
     */
    public function substituteWageForCell(array $cell, string $date, float $substituteMonthlyBase): float
    {
        $substituteId = (string) ($cell['temporary_driver_id'] ?? '');
        $ownerId = (string) ($cell['wage_owner_driver_id'] ?? '');

        if ($substituteId === '') {
            return 0.0;
        }

        if ($ownerId === $substituteId && (float) ($cell['wage_amount'] ?? 0) > 0) {
            return (float) $cell['wage_amount'];
        }

        return MonthlyWageAccrual::perDay(
            $substituteMonthlyBase,
            $date,
            PayrollPayDays::divisorForDate($date),
        );
    }

    /**
     * @return array<string, array{present: int, absent: int, leave: int, payable_days: int}>
     */
    public function driverDayStats(string $fromDate, string $toDate): array
    {
        $overview = $this->buildOverview($fromDate, $toDate);
        /** @var array<string, array<string, mixed>> $perDriverDate */
        $perDriverDate = [];

        foreach ($overview['rows'] as $row) {
            foreach ($row['days'] as $date => $cell) {
                $this->rememberDriverDay($perDriverDate, (string) $date, $cell, (string) ($cell['primary_driver_id'] ?? ''));

                if (! empty($cell['temporary_driver_id'])) {
                    $substituteCell = [
                        ...$cell,
                        'status' => AttendanceStatus::Present->value,
                        'primary_driver_id' => $cell['temporary_driver_id'],
                        'wage_owner_driver_id' => $cell['temporary_driver_id'],
                    ];
                    $this->rememberDriverDay(
                        $perDriverDate,
                        (string) $date,
                        $substituteCell,
                        (string) $cell['temporary_driver_id'],
                    );
                }
            }
        }

        return $this->aggregateDriverDayStats($perDriverDate);
    }

    /**
     * @param  array<string, array<string, mixed>>  $perDriverDate
     * @return array<string, array{present: int, absent: int, leave: int, payable_days: int}>
     */
    private function aggregateDriverDayStats(array $perDriverDate): array
    {
        /** @var array<string, array{present: int, absent: int, leave: int, payable_days: int}> $stats */
        $stats = [];

        foreach ($perDriverDate as $key => $cell) {
            [$driverId] = explode('|', $key, 2);

            if ($driverId === '') {
                continue;
            }

            if (! isset($stats[$driverId])) {
                $stats[$driverId] = ['present' => 0, 'absent' => 0, 'leave' => 0, 'payable_days' => 0];
            }

            $status = (string) ($cell['status'] ?? '');

            if ($status === '') {
                continue;
            }

            if ($status === AttendanceStatus::Present->value) {
                $stats[$driverId]['present']++;
            } elseif ($status === AttendanceStatus::NoWorkDay->value) {
                $stats[$driverId]['leave']++;
            } elseif ($status === AttendanceStatus::Absent->value && ! empty($cell['temporary_driver_id'])) {
                $stats[$driverId]['present']++;
            } else {
                $stats[$driverId]['absent']++;
            }

            if ($this->isPayableCell($cell)) {
                $stats[$driverId]['payable_days']++;
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, array<string, mixed>>  $perDriverDate
     * @param  array<string, mixed>  $cell
     */
    private function rememberDriverDay(array &$perDriverDate, string $date, array $cell, string $driverId): void
    {
        if ($driverId === '') {
            return;
        }

        $key = $driverId.'|'.$date;

        if (isset($perDriverDate[$key])) {
            return;
        }

        $perDriverDate[$key] = $cell;
    }

    /**
     * @return Collection<string, Attendance>
     */
    private function loadExceptions(string $fromDate, string $toDate): Collection
    {
        return Attendance::query()
            ->whereDate('attendance_date', '>=', $fromDate)
            ->whereDate('attendance_date', '<=', $toDate)
            ->with([
                'vehicle:id,registration_number',
                'primaryDriver:id,name,daily_wage',
                'temporaryDriver:id,name,daily_wage',
                'createdBy:id,name',
            ])
            ->get()
            ->keyBy(fn (Attendance $attendance): string => $attendance->vehicle_id.'|'.$attendance->attendance_date->toDateString());
    }

    /**
     * @return Collection<int, DriverAssignment>
     */
    private function loadAssignments(string $fromDate, string $toDate): Collection
    {
        return DriverAssignment::query()
            ->where('tenant_id', CurrentTenant::requireId())
            ->where('is_primary', true)
            ->whereDate('assigned_from', '<=', $toDate)
            ->where(function ($query) use ($fromDate) {
                $query->whereNull('assigned_to')
                    ->orWhereDate('assigned_to', '>=', $fromDate);
            })
            ->with(['driver:id,name,daily_wage,is_active', 'vehicle:id,registration_number'])
            ->get()
            ->filter(fn (DriverAssignment $assignment): bool => $assignment->driver?->is_active ?? false);
    }

    private function assignmentForDate(Collection $assignments, string $vehicleId, string $date): ?DriverAssignment
    {
        return $assignments
            ->filter(function (DriverAssignment $assignment) use ($vehicleId, $date): bool {
                if ($assignment->vehicle_id !== $vehicleId) {
                    return false;
                }

                $from = $assignment->assigned_from->toDateString();
                $to = $assignment->assigned_to?->toDateString();

                return $from <= $date && ($to === null || $to >= $date);
            })
            ->sortByDesc(fn (DriverAssignment $assignment) => $assignment->assigned_from->toDateString())
            ->first();
    }

    /**
     * @param  list<string>  $paidDriverIds
     * @return array<string, mixed>|null
     */
    private function resolveCell(
        string $date,
        ?DriverAssignment $assignment,
        ?Attendance $exception,
        array $paidDriverIds,
    ): ?array {
        if ($exception !== null) {
            return $this->cellFromException($exception, $paidDriverIds);
        }

        if ($assignment === null || $assignment->driver === null) {
            return null;
        }

        if ($this->isFutureDate($date)) {
            return $this->cellFromFutureAssignableDay($assignment);
        }

        return $this->cellFromImplicitPresent($assignment, $date, $paidDriverIds);
    }

    private function isFutureDate(string $date): bool
    {
        return $date > $this->todayInTenantTimezone();
    }

    private function todayInTenantTimezone(): string
    {
        $tenant = Tenant::query()->find(CurrentTenant::requireId());
        $timezone = $tenant?->timezone ?? config('app.timezone', 'Asia/Kolkata');

        return Carbon::now($timezone)->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    private function cellFromFutureAssignableDay(DriverAssignment $assignment): array
    {
        $driver = $assignment->driver;

        return [
            'id' => null,
            'status' => null,
            'is_implicit' => false,
            'is_future' => true,
            'primary_driver_id' => $driver->id,
            'primary_driver_name' => $driver->name,
            'temporary_driver_id' => null,
            'temporary_driver_name' => null,
            'wage_owner_driver_id' => null,
            'wage_amount' => 0,
            'wage_payment_status' => null,
            ...$this->markerFieldsForSystem(),
        ];
    }

    /**
     * @param  list<string>  $paidDriverIds
     * @return array<string, mixed>
     */
    private function cellFromImplicitPresent(DriverAssignment $assignment, string $date, array $paidDriverIds): array
    {
        $driver = $assignment->driver;
        $accrual = MonthlyWageAccrual::perDay(
            (float) $driver->daily_wage,
            $date,
            PayrollPayDays::divisorForDate($date),
        );
        $paymentStatus = in_array($driver->id, $paidDriverIds, true) ? 'paid' : 'unpaid';

        return [
            'id' => null,
            'status' => AttendanceStatus::Present->value,
            'is_implicit' => true,
            'primary_driver_id' => $driver->id,
            'primary_driver_name' => $driver->name,
            'temporary_driver_id' => null,
            'temporary_driver_name' => null,
            'wage_owner_driver_id' => $driver->id,
            'wage_amount' => $accrual,
            'substitute_payment_amount' => 0,
            'wage_payment_status' => $paymentStatus,
            ...$this->markerFieldsForSystem(),
        ];
    }

    /**
     * @param  list<string>  $paidDriverIds
     * @return array<string, mixed>
     */
    private function cellFromException(Attendance $exception, array $paidDriverIds): array
    {
        $status = $exception->status instanceof AttendanceStatus
            ? $exception->status
            : AttendanceStatus::tryFrom((string) $exception->status) ?? AttendanceStatus::Absent;

        if ($status === AttendanceStatus::Present) {
            $driver = $exception->wageOwnerDriver ?? $exception->primaryDriver;
            $attendanceDate = $exception->attendance_date->toDateString();
            $accrual = $driver !== null
                ? MonthlyWageAccrual::perDay(
                    (float) $driver->daily_wage,
                    $attendanceDate,
                    PayrollPayDays::divisorForDate($attendanceDate),
                )
                : (float) $exception->wage_amount;

            $ownerId = $exception->wage_owner_driver_id ?? $exception->primary_driver_id;
            $paymentStatus = in_array((string) $ownerId, $paidDriverIds, true) ? 'paid' : 'unpaid';

            return [
                'id' => $exception->id,
                'status' => AttendanceStatus::Present->value,
                'is_implicit' => false,
                'primary_driver_id' => $exception->primary_driver_id,
                'primary_driver_name' => $exception->primaryDriver?->name,
                'temporary_driver_id' => null,
                'temporary_driver_name' => null,
                'wage_owner_driver_id' => $exception->primary_driver_id,
                'wage_amount' => $accrual,
                'substitute_payment_amount' => 0,
                'wage_payment_status' => $paymentStatus,
                ...$this->markerFieldsForException($exception),
            ];
        }

        $ownerId = $exception->primary_driver_id;
        $paymentStatus = in_array((string) $ownerId, $paidDriverIds, true)
            ? 'paid'
            : ($exception->wage_payment_status?->value ?? $exception->wage_payment_status ?? 'unpaid');

        return [
            'id' => $exception->id,
            'status' => $status->value,
            'is_implicit' => false,
            'primary_driver_id' => $exception->primary_driver_id,
            'primary_driver_name' => $exception->primaryDriver?->name,
            'temporary_driver_id' => $exception->temporary_driver_id,
            'temporary_driver_name' => $exception->temporaryDriver?->name,
            'wage_owner_driver_id' => $ownerId,
            'wage_amount' => (float) $exception->wage_amount,
            'substitute_payment_amount' => (float) $exception->substitute_payment_amount,
            'wage_payment_status' => $paymentStatus,
            ...$this->markerFieldsForException($exception),
        ];
    }

    /**
     * @return array{marked_source: string, marked_by_name: ?string, marked_via: ?string}
     */
    private function markerFieldsForSystem(): array
    {
        return [
            'marked_source' => 'system',
            'marked_by_name' => null,
            'marked_via' => null,
        ];
    }

    /**
     * @return array{marked_source: string, marked_by_name: ?string, marked_via: ?string}
     */
    private function markerFieldsForException(Attendance $exception): array
    {
        $markedVia = $exception->marked_via instanceof AttendanceMarkedVia
            ? $exception->marked_via->value
            : $exception->marked_via;

        return [
            'marked_source' => 'admin',
            'marked_by_name' => $exception->createdBy?->name,
            'marked_via' => is_string($markedVia) ? $markedVia : null,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $grouped
     * @param  Collection<string, Vehicle>  $vehicles
     * @param  array<string, mixed>  $cell
     */
    private function addCellToGrouped(
        array &$grouped,
        Collection $vehicles,
        string $vehicleId,
        string $date,
        array $cell,
    ): void {
        if (! isset($grouped[$vehicleId])) {
            $grouped[$vehicleId] = [
                'vehicle_id' => $vehicleId,
                'vehicle_label' => $vehicles->get($vehicleId)?->registration_number ?? '—',
                'driver_name' => '—',
                'days' => [],
            ];
        }

        $grouped[$vehicleId]['days'][$date] = $cell;
    }

    private function referenceDateForDriverLabel(string $fromDate, string $toDate): string
    {
        $today = $this->todayInTenantTimezone();

        if ($today < $fromDate) {
            return $fromDate;
        }

        if ($today > $toDate) {
            return $toDate;
        }

        return $today;
    }

    /**
     * @param  array{exceptions: int, present: int, absent: int, no_work: int, paid: int, unpaid: int}  $summary
     * @param  array<string, mixed>  $cell
     */
    private function incrementSummary(array &$summary, array $cell): void
    {
        $status = (string) ($cell['status'] ?? '');

        if ($status === '') {
            return;
        }

        if ($status === AttendanceStatus::Present->value) {
            $summary['present']++;
        } elseif ($status === AttendanceStatus::NoWorkDay->value) {
            $summary['no_work']++;
        } else {
            $summary['absent']++;
        }

        if ($this->isPayableCell($cell)) {
            if (($cell['wage_payment_status'] ?? '') === 'paid') {
                $summary['paid']++;
            } else {
                $summary['unpaid']++;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $cell
     */
    private function isPayableCell(array $cell): bool
    {
        return (float) ($cell['wage_amount'] ?? 0) > 0;
    }

    /**
     * @return list<string>
     */
    private function dateRange(string $fromDate, string $toDate): array
    {
        $dates = [];
        $cursor = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->startOfDay();

        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }
}
