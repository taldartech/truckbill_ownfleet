<?php

namespace App\Modules\DriverManagement\Services;

use App\Attendance;
use App\Modules\DriverManagement\Repositories\AttendanceRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AttendanceService
{
    public function __construct(private readonly AttendanceRepository $attendanceRepository)
    {
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->attendanceRepository->paginate($perPage);
    }

    public function mark(array $payload): Attendance
    {
        $payload['wage_owner_driver_id'] = $payload['primary_driver_id'];

        return $this->attendanceRepository->upsertByVehicleAndDate(
            [
                'vehicle_id' => $payload['vehicle_id'],
                'attendance_date' => $payload['attendance_date'],
            ],
            $payload
        );
    }

    public function update(Attendance $attendance, array $payload): Attendance
    {
        $payload['wage_owner_driver_id'] = $payload['primary_driver_id'];

        return $this->attendanceRepository->update($attendance, $payload);
    }

    public function delete(Attendance $attendance): void
    {
        $this->attendanceRepository->delete($attendance);
    }
}
