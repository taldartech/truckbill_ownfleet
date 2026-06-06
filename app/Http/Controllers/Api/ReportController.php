<?php

namespace App\Http\Controllers\Api;

use App\Attendance;
use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function attendance(Request $request): JsonResponse
    {
        $this->authorize('reports.view');

        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $perPage = min(max((int) $request->input('per_page', 50), 1), 500);

        $query = Attendance::query()
            ->with(['vehicle', 'primaryDriver', 'temporaryDriver', 'wageOwnerDriver']);

        if ($fromDate !== null) {
            $query->whereDate('attendance_date', '>=', $fromDate);
        }

        if ($toDate !== null) {
            $query->whereDate('attendance_date', '<=', $toDate);
        }

        return AttendanceResource::collection($query->latest('attendance_date')->paginate($perPage))->response();
    }

    public function trips(): JsonResponse
    {
        $this->authorize('trip_reports.view');

        return response()->json(
            Trip::query()
                ->with(['vehicle', 'driver', 'routeLegs'])
                ->latest()
                ->paginate(min(max((int) request()->input('per_page', 50), 1), 500)),
        );
    }
}
