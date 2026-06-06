<?php

namespace App\Http\Controllers\Api;

use App\Attendance;
use App\Driver;
use App\Http\Controllers\Controller;
use App\Trip;
use App\Vehicle;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('dashboard.view');

        return response()->json([
            'vehicles_count' => Vehicle::query()->count(),
            'drivers_count' => Driver::query()->count(),
            'trips_count' => Trip::query()->count(),
            'present_today_count' => Attendance::query()->whereDate('attendance_date', now()->toDateString())->count(),
        ]);
    }
}
