<?php

namespace App\Modules\DriverManagement\Services;

use App\AttendanceMonthLock;
use App\Driver;
use App\Enums\AttendanceStatus;
use App\MonthlyDriverWage;
use App\Support\MonthlySettlementCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class MonthlySettlementService
{
    public function __construct(
        private readonly AttendanceCalendarService $attendanceCalendar,
        private readonly AttendanceMonthLockService $monthLockService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function sheet(string $month): array
    {
        [$monthStart, $monthEnd] = $this->monthBoundaries($month);
        $calendarDays = (int) Carbon::parse($monthStart)->daysInMonth;
        $driverStats = $this->attendanceCalendar->driverPrimaryDayStats($monthStart, $monthEnd);
        $substituteEarnings = $this->attendanceCalendar->driverSubstituteEarnings($monthStart, $monthEnd);
        $driverTotals = $this->attendanceCalendar->driverWageTotals($monthStart, $monthEnd);
        $overview = $this->attendanceCalendar->buildOverview($monthStart, $monthEnd);
        $lock = $this->findMonthLock($monthStart);

        if ($lock !== null && $lock->status->value !== 'open') {
            $this->refreshMonthCalculations($month);
        }

        $wages = MonthlyDriverWage::query()
            ->whereDate('month', $monthStart)
            ->with('driver')
            ->orderBy('driver_id')
            ->get()
            ->keyBy('driver_id');

        $driverIds = collect(array_keys($driverTotals))
            ->merge(array_keys($driverStats))
            ->merge(array_keys($substituteEarnings))
            ->merge($wages->keys())
            ->unique()
            ->values();

        $drivers = Driver::query()
            ->whereIn('id', $driverIds)
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $payrollLines = [];
        $serial = 0;

        foreach ($driverIds as $driverId) {
            $driver = $drivers->get($driverId);

            if ($driver === null) {
                continue;
            }

            $wage = $wages->get($driverId);
            $stats = $driverStats[$driverId] ?? ['present' => 0, 'absent' => 0, 'leave' => 0, 'payable_days' => 0];
            $substitute = $substituteEarnings[$driverId] ?? ['days' => 0, 'total_wage' => 0.0];

            if ($wage === null && ! isset($driverTotals[$driverId]) && $substitute['days'] === 0) {
                continue;
            }

            $serial++;
            $isSubstituteOnly = ((int) $stats['present'] + (int) $stats['absent'] + (int) $stats['leave']) === 0
                && (int) ($substitute['days'] ?? 0) > 0;
            $payrollLines[] = $this->formatPayrollLine(
                $serial,
                $driver,
                $wage,
                $stats,
                $calendarDays,
                $substitute,
                $isSubstituteOnly,
            );
        }

        return [
            'month' => Carbon::parse($monthStart)->format('Y-m'),
            'month_label' => Carbon::parse($monthStart)->format('F Y'),
            'calendar_days' => $calendarDays,
            'month_lock' => $this->monthLockService->summaryForMonth($month),
            'vehicle_settlement' => $lock?->vehicle_settlement ?? [],
            'payroll_lines' => $payrollLines,
            'vehicle_matrix' => $this->buildVehicleMatrix($overview, $monthStart, $calendarDays, $lock),
        ];
    }

    public function updatePayrollLine(MonthlyDriverWage $monthlyDriverWage, array $payload, ?string $actorId = null): MonthlyDriverWage
    {
        $this->assertMonthNotLocked($monthlyDriverWage);

        if (array_key_exists('extra_deductions', $payload)) {
            $monthlyDriverWage->pf = 0;
            $monthlyDriverWage->esi = 0;
            $monthlyDriverWage->other_deduction = (float) $payload['extra_deductions'];
            $monthlyDriverWage->hra = 0;
            $monthlyDriverWage->holiday_allowance = 0;
        }

        $monthlyDriverWage->fill([
            'absent_deduction' => $payload['absent_deduction'] ?? $monthlyDriverWage->absent_deduction,
            'base_salary' => $payload['base_salary'] ?? $monthlyDriverWage->base_salary,
            'hra' => $payload['hra'] ?? $monthlyDriverWage->hra,
            'holiday_allowance' => $payload['holiday_allowance'] ?? $monthlyDriverWage->holiday_allowance,
            'pf' => $payload['pf'] ?? $monthlyDriverWage->pf,
            'esi' => $payload['esi'] ?? $monthlyDriverWage->esi,
            'other_deduction' => $payload['other_deduction'] ?? $monthlyDriverWage->other_deduction,
            'gross_earning' => $payload['gross_earning'] ?? $monthlyDriverWage->gross_earning,
            'remarks' => array_key_exists('remarks', $payload) ? $payload['remarks'] : $monthlyDriverWage->remarks,
            'round_net' => $payload['round_net'] ?? $monthlyDriverWage->round_net,
            'updated_by' => $actorId,
        ]);

        $monthlyDriverWage->net_salary = MonthlySettlementCalculator::netSalary($monthlyDriverWage);
        $monthlyDriverWage->total_wage = $monthlyDriverWage->gross_earning;
        $monthlyDriverWage->save();

        return $monthlyDriverWage->fresh('driver');
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public function updateVehicleLine(string $month, array $line): array
    {
        [$monthStart] = $this->monthBoundaries($month);
        $lock = $this->findMonthLock($monthStart);
        $vehicleId = (string) ($line['vehicle_id'] ?? '');

        if ($lock === null) {
            throw ValidationException::withMessages([
                'month' => ['Close the month before saving vehicle settlement lines.'],
            ]);
        }

        if ($lock->status->value === 'paid') {
            throw ValidationException::withMessages([
                'month' => ['This month is locked. Unlock it before editing settlement.'],
            ]);
        }

        /** @var array<string, array<string, mixed>> $vehicleSettlement */
        $vehicleSettlement = $lock->vehicle_settlement ?? [];
        $rowKey = (string) ($line['row_key'] ?? $vehicleId);
        $vehicleSettlement[$rowKey] = [
            'remarks' => $line['remarks'] ?? '',
            'salary' => isset($line['salary']) ? (float) $line['salary'] : null,
            'total' => isset($line['total']) ? (float) $line['total'] : null,
        ];

        $lock->vehicle_settlement = $vehicleSettlement;
        $lock->save();

        return $vehicleSettlement[$rowKey];
    }

    /**
     * @param  array{present: int, absent: int, leave: int, payable_days: int}  $stats
     * @param  array{days: int, total_wage: float}  $substitute
     * @return array<string, mixed>
     */
    public function formatPayrollLine(
        int $serial,
        Driver $driver,
        ?MonthlyDriverWage $wage,
        array $stats,
        int $calendarDays,
        array $substitute = ['days' => 0, 'total_wage' => 0.0],
        bool $isSubstituteOnly = false,
    ): array {
        $monthlyBase = (float) $driver->daily_wage;
        $dailyRate = MonthlySettlementCalculator::dailyRate($monthlyBase, $calendarDays);
        $presentDays = (int) $stats['present'];
        $absentDays = (int) $stats['absent'];
        $leaveDays = (int) $stats['leave'];
        $substituteDays = (int) ($substitute['days'] ?? 0);
        $substitutePay = round((float) ($substitute['total_wage'] ?? 0), 2);
        $primaryGross = round($presentDays * $dailyRate, 2);
        $gross = round($primaryGross + $substitutePay, 2);
        $absentDeduction = MonthlySettlementCalculator::absentDeductionForDays(
            $absentDays,
            $monthlyBase,
            $calendarDays,
        );

        $extraDeductions = $wage !== null ? MonthlySettlementCalculator::extraDeductions($wage) : 0.0;

        if ($wage !== null) {
            $wage->absent_deduction = $absentDeduction;
            $wage->gross_earning = $gross;
            $net = MonthlySettlementCalculator::netSalary($wage);
        } else {
            $net = max(0, $gross - $extraDeductions);
        }

        return [
            'serial' => $serial,
            'monthly_wage_id' => $wage?->id,
            'driver_id' => $driver->id,
            'is_substitute' => $isSubstituteOnly,
            'name' => $driver->name,
            'aadhaar_number' => $driver->aadhaar_number,
            'bank_account_number' => $driver->bank_account_number,
            'bank_ifsc' => $driver->bank_ifsc,
            'monthly_base' => $monthlyBase,
            'daily_rate' => $dailyRate,
            'calendar_days' => $wage?->calendar_days ?? $calendarDays,
            'pay_days' => $calendarDays,
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'leave_days' => $leaveDays,
            'substitute_days' => $substituteDays,
            'substitute_pay' => $substitutePay,
            'primary_gross' => $primaryGross,
            'gross_earning' => $gross,
            'absent_deduction' => $absentDeduction,
            'base_salary' => $wage?->base_salary ?? $monthlyBase,
            'extra_deductions' => $extraDeductions,
            'total_deduction' => $wage !== null ? MonthlySettlementCalculator::totalDeductions($wage) : 0,
            'net_salary' => $net,
            'net_salary_payable' => $net,
            'round_net' => (bool) ($wage?->round_net ?? false),
            'remarks' => $wage?->remarks,
            'payment_status' => $wage?->payment_status?->value ?? $wage?->payment_status ?? 'unpaid',
            'paid_at' => $wage?->paid_at?->toISOString(),
        ];
    }

    /**
     * @param  array{summary: array<string, int>, rows: list<array<string, mixed>>}  $overview
     * @return list<array<string, mixed>>
     */
    private function buildVehicleMatrix(array $overview, string $monthStart, int $calendarDays, ?AttendanceMonthLock $lock): array
    {
        /** @var array<string, array<string, mixed>> $vehicleSettlement */
        $vehicleSettlement = $lock?->vehicle_settlement ?? [];
        $matrix = [];
        $serial = 0;

        foreach ($overview['rows'] as $row) {
            $expandedRows = $this->expandVehicleDriverRows($row, $monthStart, $calendarDays);

            if ($expandedRows === []) {
                continue;
            }

            $showVehicleLabel = true;

            foreach ($expandedRows as $expanded) {
                $serial++;
                $rowKey = (string) $expanded['row_key'];
                $saved = $vehicleSettlement[$rowKey] ?? $vehicleSettlement[(string) $row['vehicle_id']] ?? [];

                $calculatedSalary = round((float) $expanded['salary'], 2);
                $savedSalary = array_key_exists('salary', $saved) ? (float) $saved['salary'] : null;
                $savedTotal = array_key_exists('total', $saved) ? (float) $saved['total'] : null;
                $salary = $this->resolveVehicleLineAmount($savedSalary, $calculatedSalary);
                $total = $this->resolveVehicleLineAmount($savedTotal, $calculatedSalary);

                if ($total <= 0 && $salary > 0) {
                    $total = $salary;
                }

                $matrix[] = [
                    'serial' => $serial,
                    'row_key' => $rowKey,
                    'vehicle_id' => $expanded['vehicle_id'],
                    'driver_id' => $expanded['driver_id'],
                    'vehicle_label' => $showVehicleLabel ? $expanded['vehicle_label'] : '',
                    'driver_name' => $expanded['is_substitute']
                        ? '~ '.$expanded['driver_name']
                        : $expanded['driver_name'],
                    'is_substitute' => $expanded['is_substitute'],
                    'days' => $expanded['days'],
                    'salary' => $salary,
                    'remarks' => $saved['remarks'] ?? '',
                    'total' => $total,
                ];

                $showVehicleLabel = false;
            }
        }

        return $matrix;
    }

    /**
     * @param  array<string, mixed>  $sourceRow
     * @return list<array<string, mixed>>
     */
    private function expandVehicleDriverRows(array $sourceRow, string $monthStart, int $calendarDays): array
    {
        /** @var array<string, array<string, mixed>> $perDriver */
        $perDriver = [];
        $primaryName = trim((string) ($sourceRow['driver_name'] ?? ''));
        /** @var array<string, mixed> $daysByDate */
        $daysByDate = $sourceRow['days'] ?? [];
        /** @var array<string, float> $substituteBases */
        $substituteBases = [];

        $ensureRowKey = function (string $driverName, ?string $driverId, bool $isSubstitute) use ($sourceRow, &$perDriver): ?string {
            $normalizedName = trim($driverName);

            if ($normalizedName === '') {
                return null;
            }

            $key = $driverId !== null && $driverId !== '' ? $driverId : $normalizedName;

            if (! isset($perDriver[$key])) {
                $perDriver[$key] = [
                    'row_key' => $sourceRow['vehicle_id'].'|'.$key,
                    'vehicle_id' => (string) $sourceRow['vehicle_id'],
                    'vehicle_label' => (string) $sourceRow['vehicle_label'],
                    'driver_id' => $driverId,
                    'driver_name' => $normalizedName,
                    'is_substitute' => $isSubstitute,
                    'days' => [],
                    'salary' => 0.0,
                ];
            }

            return $key;
        };

        foreach ($daysByDate as $date => $cell) {
            if (! is_array($cell)) {
                continue;
            }

            $day = (int) Carbon::parse((string) $date)->format('j');

            if ($day < 1 || $day > $calendarDays) {
                continue;
            }

            $primaryDriverId = (string) ($cell['primary_driver_id'] ?? $cell['wage_owner_driver_id'] ?? '');
            $primaryCellName = trim((string) ($cell['primary_driver_name'] ?? $primaryName));
            $substituteName = trim((string) ($cell['temporary_driver_name'] ?? ''));

            if ($primaryCellName !== '' || $primaryDriverId !== '') {
                $primaryKey = $ensureRowKey(
                    $primaryCellName !== '' ? $primaryCellName : $primaryName,
                    $primaryDriverId !== '' ? $primaryDriverId : null,
                    false,
                );

                if ($primaryKey !== null) {
                    $perDriver[$primaryKey]['days'][$day] = $this->dayMark($cell);

                    if ($substituteName === '') {
                        $ownerId = (string) ($cell['wage_owner_driver_id'] ?? $primaryDriverId);
                        $rowDriverId = (string) ($perDriver[$primaryKey]['driver_id'] ?? $primaryDriverId);

                        if ($rowDriverId !== '' && $ownerId === $rowDriverId) {
                            $perDriver[$primaryKey]['salary'] += (float) ($cell['wage_amount'] ?? 0);
                        } elseif ($rowDriverId === '' && $primaryName === $primaryCellName) {
                            $perDriver[$primaryKey]['salary'] += (float) ($cell['wage_amount'] ?? 0);
                        }
                    }
                }
            }

            if ($substituteName !== '') {
                $substituteKey = $ensureRowKey(
                    $substituteName,
                    (string) ($cell['temporary_driver_id'] ?? '') ?: null,
                    true,
                );

                if ($substituteKey !== null) {
                    $perDriver[$substituteKey]['days'][$day] = 'P';
                    $substituteId = (string) ($cell['temporary_driver_id'] ?? '');

                    if ($substituteId !== '') {
                        $perDriver[$substituteKey]['salary'] += $this->attendanceCalendar->substituteWageForCell(
                            $cell,
                            (string) $date,
                            $this->substituteMonthlyBase($substituteId, $substituteBases),
                        );
                    }
                }
            }
        }

        if ($perDriver === [] && $primaryName !== '' && $daysByDate !== []) {
            $fallbackId = $this->firstDriverIdFromDays($daysByDate) ?? $primaryName;
            $perDriver[$fallbackId] = [
                'row_key' => $sourceRow['vehicle_id'].'|'.$fallbackId,
                'vehicle_id' => (string) $sourceRow['vehicle_id'],
                'vehicle_label' => (string) $sourceRow['vehicle_label'],
                'driver_id' => is_string($fallbackId) && strlen($fallbackId) === 36 ? $fallbackId : null,
                'driver_name' => $primaryName,
                'is_substitute' => false,
                'days' => $this->dayMarksFromOverviewDays($daysByDate, $calendarDays),
                'salary' => $this->sumWageFromDays($daysByDate),
            ];
        }

        $rows = array_values($perDriver);

        usort($rows, function (array $left, array $right): int {
            if ($left['is_substitute'] !== $right['is_substitute']) {
                return $left['is_substitute'] ? 1 : -1;
            }

            return strcmp($left['driver_name'], $right['driver_name']);
        });

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $cell
     */
    private function isPayableCell(array $cell): bool
    {
        return (float) ($cell['wage_amount'] ?? 0) > 0;
    }

    /**
     * @param  array<string, mixed>  $daysByDate
     * @return array<int, string>
     */
    private function dayMarksFromOverviewDays(array $daysByDate, int $calendarDays): array
    {
        $marks = [];

        foreach ($daysByDate as $date => $cell) {
            if (! is_array($cell)) {
                continue;
            }

            $day = (int) Carbon::parse((string) $date)->format('j');

            if ($day >= 1 && $day <= $calendarDays) {
                $marks[$day] = $this->dayMark($cell);
            }
        }

        return $marks;
    }

    /**
     * @param  array<string, mixed>  $daysByDate
     */
    private function sumWageFromDays(array $daysByDate): float
    {
        $total = 0.0;

        foreach ($daysByDate as $cell) {
            if (is_array($cell) && $this->isPayableCell($cell)) {
                $total += (float) ($cell['wage_amount'] ?? 0);
            }
        }

        return round($total, 2);
    }

    /**
     * @param  array<string, mixed>  $daysByDate
     */
    private function firstDriverIdFromDays(array $daysByDate): ?string
    {
        foreach ($daysByDate as $cell) {
            if (! is_array($cell)) {
                continue;
            }

            $id = (string) ($cell['primary_driver_id'] ?? $cell['wage_owner_driver_id'] ?? '');

            if ($id !== '') {
                return $id;
            }
        }

        return null;
    }

    private function resolveVehicleLineAmount(?float $saved, float $calculated): float
    {
        if ($saved === null) {
            return $calculated;
        }

        if ($saved <= 0 && $calculated > 0) {
            return $calculated;
        }

        return $saved;
    }

    /**
     * @param  array<string, float>  $cache
     */
    private function substituteMonthlyBase(string $driverId, array &$cache): float
    {
        if (! array_key_exists($driverId, $cache)) {
            $cache[$driverId] = (float) (Driver::query()->find($driverId)?->daily_wage ?? 0);
        }

        return $cache[$driverId];
    }

    /**
     * @param  array<string, mixed>|null  $cell
     */
    private function dayMark(?array $cell): string
    {
        if ($cell === null) {
            return '';
        }

        $status = (string) ($cell['status'] ?? '');

        if ($status === AttendanceStatus::Present->value) {
            return 'P';
        }

        if ($status === AttendanceStatus::NoWorkDay->value) {
            return 'N';
        }

        if (! empty($cell['temporary_driver_id'])) {
            return 'A';
        }

        return 'A';
    }

    public function applySettlementDefaults(
        MonthlyDriverWage $monthlyWage,
        Driver $driver,
        array $stats,
        int $calendarDays,
        array $substitute = ['days' => 0, 'total_wage' => 0.0],
    ): void {
        $monthlyBase = (float) $driver->daily_wage;
        $dailyRate = MonthlySettlementCalculator::dailyRate($monthlyBase, $calendarDays);
        $presentDays = (int) $stats['present'];
        $absentDays = (int) $stats['absent'];
        $leaveDays = (int) $stats['leave'];
        $substitutePay = round((float) ($substitute['total_wage'] ?? 0), 2);
        $primaryGross = round($presentDays * $dailyRate, 2);
        $gross = round($primaryGross + $substitutePay, 2);

        $monthlyWage->fill([
            'calendar_days' => $calendarDays,
            'present_days' => $presentDays,
            'absent_days' => $absentDays,
            'leave_days' => $leaveDays,
            'payable_days' => $presentDays,
            'total_days' => $presentDays + $absentDays + $leaveDays,
            'gross_earning' => $gross,
            'total_wage' => $gross,
            'base_salary' => $monthlyBase,
            'absent_deduction' => MonthlySettlementCalculator::absentDeductionForDays(
                $absentDays,
                $monthlyBase,
                $calendarDays,
            ),
            'hra' => 0,
            'holiday_allowance' => 0,
            'pf' => 0,
            'esi' => 0,
        ]);

        $monthlyWage->net_salary = MonthlySettlementCalculator::netSalary($monthlyWage);
    }

    public function refreshMonthCalculations(string $month): int
    {
        [$monthStart, $monthEnd] = $this->monthBoundaries($month);
        $calendarDays = (int) Carbon::parse($monthStart)->daysInMonth;
        $driverStats = $this->attendanceCalendar->driverPrimaryDayStats($monthStart, $monthEnd);
        $substituteEarnings = $this->attendanceCalendar->driverSubstituteEarnings($monthStart, $monthEnd);
        $updated = 0;

        $wages = MonthlyDriverWage::query()
            ->whereDate('month', $monthStart)
            ->with('driver')
            ->get();

        foreach ($wages as $monthlyWage) {
            $driver = $monthlyWage->driver;

            if ($driver === null) {
                continue;
            }

            $stats = $driverStats[$monthlyWage->driver_id] ?? ['present' => 0, 'absent' => 0, 'leave' => 0, 'payable_days' => 0];
            $substitute = $substituteEarnings[$monthlyWage->driver_id] ?? ['days' => 0, 'total_wage' => 0.0];
            $this->applySettlementDefaults($monthlyWage, $driver, $stats, $calendarDays, $substitute);
            $monthlyWage->save();
            $updated++;
        }

        return $updated;
    }

    private function assertMonthNotLocked(MonthlyDriverWage $monthlyDriverWage): void
    {
        $month = Carbon::parse($monthlyDriverWage->month)->format('Y-m');

        if ($this->monthLockService->statusForMonth($month)->value === 'paid') {
            throw ValidationException::withMessages([
                'month' => ['This month is locked. Unlock it before editing settlement.'],
            ]);
        }
    }

    private function findMonthLock(string $monthStart): ?AttendanceMonthLock
    {
        return AttendanceMonthLock::query()
            ->whereDate('month', $monthStart)
            ->first();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function monthBoundaries(string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }
}
