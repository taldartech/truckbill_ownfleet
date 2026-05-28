<?php

namespace App\Http\Controllers\Api;

use App\Attendance;
use App\Trip;
use Illuminate\Http\JsonResponse;

class ReportController
{
    public function attendance(): JsonResponse
    {
        $fromDate = request()->query('from_date');
        $toDate = request()->query('to_date');

        $query = Attendance::query();
        if ($fromDate !== null) {
            $query->whereDate('attendance_date', '>=', $fromDate);
        }

        if ($toDate !== null) {
            $query->whereDate('attendance_date', '<=', $toDate);
        }

        return response()->json($query->paginate());
    }

    public function trips(): JsonResponse
    {
        return response()->json(Trip::query()->with('routeLegs')->latest()->paginate());
    }
}
