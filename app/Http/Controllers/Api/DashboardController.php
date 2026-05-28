<?php

namespace App\Http\Controllers\Api;

use App\Attendance;
use App\Driver;
use App\Trip;
use App\Vehicle;
use Illuminate\Http\JsonResponse;

class DashboardController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'vehicles_count' => Vehicle::query()->count(),
            'drivers_count' => Driver::query()->count(),
            'trips_count' => Trip::query()->count(),
            'present_today_count' => Attendance::query()->whereDate('attendance_date', now()->toDateString())->count(),
        ]);
    }
}
