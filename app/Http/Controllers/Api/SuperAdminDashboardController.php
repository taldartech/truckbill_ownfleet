<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdminDashboardRequest;
use App\Support\ReportDateRange;
use App\Support\SuperAdminDashboardStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class SuperAdminDashboardController extends Controller
{
    public function stats(SuperAdminDashboardRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $fromDate = $validated['from_date'] ?? Carbon::today()->startOfMonth()->toDateString();
        $toDate = $validated['to_date'] ?? Carbon::today()->toDateString();

        ReportDateRange::assertValidDates($fromDate, $toDate, 'from_date', 'to_date');

        return response()->json(SuperAdminDashboardStats::build(
            $request->user(),
            $fromDate,
            $toDate,
            $validated['company_id'] ?? null,
            (int) ($validated['trend_months'] ?? SuperAdminDashboardStats::DEFAULT_TREND_MONTHS),
        ));
    }
}
