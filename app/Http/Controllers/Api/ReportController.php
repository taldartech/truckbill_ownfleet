<?php

namespace App\Http\Controllers\Api;

use App\Attendance;
use App\Http\Resources\AttendanceResource;
use App\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController
{
    public function attendance(Request $request): JsonResponse
    {
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $query = Attendance::query()
            ->with(['vehicle', 'primaryDriver', 'temporaryDriver', 'wageOwnerDriver']);

        if ($fromDate !== null) {
            $query->whereDate('attendance_date', '>=', $fromDate);
        }

        if ($toDate !== null) {
            $query->whereDate('attendance_date', '<=', $toDate);
        }

        return AttendanceResource::collection($query->latest('attendance_date')->paginate())->response();
    }

    public function trips(): JsonResponse
    {
        return response()->json(Trip::query()->with('routeLegs')->latest()->paginate());
    }
}
