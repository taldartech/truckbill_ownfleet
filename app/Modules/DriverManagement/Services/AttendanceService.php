<?php

namespace App\Modules\DriverManagement\Services;

use App\Attendance;
use App\Driver;
use App\Enums\AttendanceMarkedVia;
use App\Enums\AttendanceStatus;
use App\Enums\WagePaymentStatus;
use App\Modules\DriverManagement\Repositories\AttendanceRepository;
use App\Support\CurrentTenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(private readonly AttendanceRepository $attendanceRepository) {}

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
        return $this->attendanceRepository->monthOverview($fromDate, $toDate);
    }

    public function mark(array $payload): Attendance
    {
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

    public function delete(Attendance $attendance): void
    {
        $this->attendanceRepository->delete($attendance);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function preparePayload(array $payload, ?Attendance $existing = null): array
    {
        $status = AttendanceStatus::from(strtoupper((string) $payload['status']));

        /** @var Driver $primaryDriver */
        $primaryDriver = Driver::query()->findOrFail($payload['primary_driver_id']);

        $payload['status'] = $status->value;
        $payload['wage_owner_driver_id'] = $primaryDriver->id;

        if ($status === AttendanceStatus::Present) {
            $payload['temporary_driver_id'] = null;
            $payload['temporary_assignment_remarks'] = null;
        }

        if (! isset($payload['marked_via'])) {
            $payload['marked_via'] = AttendanceMarkedVia::AdminPanel->value;
        }

        if (! array_key_exists('wage_amount', $payload) || $payload['wage_amount'] === null) {
            $payload['wage_amount'] = $this->resolveWageAmount($status, $primaryDriver, $payload);
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
    private function resolveWageAmount(AttendanceStatus $status, Driver $primaryDriver, array $payload): float
    {
        if ($status === AttendanceStatus::Present) {
            return (float) $primaryDriver->daily_wage;
        }

        if ($status === AttendanceStatus::Absent && ! empty($payload['temporary_driver_id'])) {
            return (float) $primaryDriver->daily_wage;
        }

        return 0;
    }
}
