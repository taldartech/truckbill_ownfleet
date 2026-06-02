<?php

namespace App\Modules\DriverManagement\Repositories;

use App\Attendance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AttendanceRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->with(['vehicle', 'primaryDriver', 'temporaryDriver'])
            ->latest('attendance_date')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @return array{summary: array{marked: int, present: int, absent: int, paid: int, unpaid: int}, rows: list<array<string, mixed>>}
     */
    public function monthOverview(string $fromDate, string $toDate): array
    {
        $summaryRow = Attendance::query()
            ->whereDate('attendance_date', '>=', $fromDate)
            ->whereDate('attendance_date', '<=', $toDate)
            ->selectRaw('COUNT(*) as marked')
            ->selectRaw("SUM(CASE WHEN status = 'PRESENT' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN status != 'PRESENT' THEN 1 ELSE 0 END) as absent")
            ->selectRaw("SUM(CASE WHEN wage_amount > 0 AND wage_payment_status = 'paid' THEN 1 ELSE 0 END) as paid")
            ->selectRaw("SUM(CASE WHEN wage_amount > 0 AND wage_payment_status = 'unpaid' THEN 1 ELSE 0 END) as unpaid")
            ->first();

        $records = Attendance::query()
            ->select([
                'id',
                'vehicle_id',
                'primary_driver_id',
                'temporary_driver_id',
                'attendance_date',
                'status',
                'wage_amount',
                'wage_payment_status',
            ])
            ->whereDate('attendance_date', '>=', $fromDate)
            ->whereDate('attendance_date', '<=', $toDate)
            ->with([
                'vehicle:id,registration_number',
                'primaryDriver:id,name',
                'temporaryDriver:id,name',
            ])
            ->orderBy('vehicle_id')
            ->orderBy('attendance_date')
            ->get();

        return [
            'summary' => [
                'marked' => (int) ($summaryRow->marked ?? 0),
                'present' => (int) ($summaryRow->present ?? 0),
                'absent' => (int) ($summaryRow->absent ?? 0),
                'paid' => (int) ($summaryRow->paid ?? 0),
                'unpaid' => (int) ($summaryRow->unpaid ?? 0),
            ],
            'rows' => $this->groupOverviewRows($records),
        ];
    }

    public function upsertByVehicleAndDate(array $identity, array $values): Attendance
    {
        return Attendance::query()->updateOrCreate($identity, $values);
    }

    public function update(Attendance $attendance, array $values): Attendance
    {
        $attendance->update($values);

        return $attendance->fresh([
            'vehicle',
            'primaryDriver',
            'temporaryDriver',
            'wageOwnerDriver',
        ]);
    }

    public function delete(Attendance $attendance): void
    {
        $attendance->delete();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;
        $status = isset($filters['status']) ? strtoupper((string) $filters['status']) : null;

        return Attendance::query()
            ->when($fromDate !== null, fn (Builder $query) => $query->whereDate('attendance_date', '>=', $fromDate))
            ->when($toDate !== null, fn (Builder $query) => $query->whereDate('attendance_date', '<=', $toDate))
            ->when(! empty($filters['vehicle_id']), fn (Builder $query) => $query->where('vehicle_id', $filters['vehicle_id']))
            ->when(! empty($filters['primary_driver_id']), fn (Builder $query) => $query->where('primary_driver_id', $filters['primary_driver_id']))
            ->when($status !== null && $status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when(! empty($filters['wage_payment_status']), fn (Builder $query) => $query->where('wage_payment_status', $filters['wage_payment_status']));
    }

    /**
     * @param  Collection<int, Attendance>  $records
     * @return list<array<string, mixed>>
     */
    private function groupOverviewRows(Collection $records): array
    {
        /** @var array<string, array<string, mixed>> $grouped */
        $grouped = [];

        foreach ($records as $record) {
            $vehicleId = $record->vehicle_id;

            if (! isset($grouped[$vehicleId])) {
                $grouped[$vehicleId] = [
                    'vehicle_id' => $vehicleId,
                    'vehicle_label' => $record->vehicle?->registration_number ?? '—',
                    'driver_name' => $record->primaryDriver?->name ?? '—',
                    'days' => [],
                ];
            }

            if ($record->primaryDriver?->name) {
                $grouped[$vehicleId]['driver_name'] = $record->primaryDriver->name;
            }

            $grouped[$vehicleId]['days'][$record->attendance_date->toDateString()] = [
                'id' => $record->id,
                'status' => $record->status,
                'temporary_driver_id' => $record->temporary_driver_id,
                'temporary_driver_name' => $record->temporaryDriver?->name,
                'primary_driver_name' => $record->primaryDriver?->name,
                'wage_amount' => $record->wage_amount,
                'wage_payment_status' => $record->wage_payment_status?->value ?? $record->wage_payment_status,
            ];
        }

        $rows = array_values($grouped);

        usort($rows, fn (array $left, array $right): int => strcmp($left['vehicle_label'], $right['vehicle_label']));

        return $rows;
    }
}
