<?php

namespace App\Modules\DriverManagement\Repositories;

use App\Attendance;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AttendanceRepository
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Attendance::query()->latest('attendance_date')->paginate($perPage);
    }

    public function upsertByVehicleAndDate(array $identity, array $values): Attendance
    {
        return Attendance::query()->updateOrCreate($identity, $values);
    }

    public function update(Attendance $attendance, array $values): Attendance
    {
        $attendance->update($values);

        return $attendance->fresh();
    }

    public function delete(Attendance $attendance): void
    {
        $attendance->delete();
    }
}
