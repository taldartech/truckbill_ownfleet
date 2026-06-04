<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\TyreManagement\Services\TyreReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TyreReportController extends Controller
{
    public function __construct(private readonly TyreReportService $tyreReportService) {}

    public function tyreLife(Request $request): JsonResponse
    {
        $this->authorize('tyre_reports.view');

        return response()->json(['data' => $this->tyreReportService->tyreLife($this->filters($request))]);
    }

    public function costPerKm(Request $request): JsonResponse
    {
        $this->authorize('tyre_reports.view');

        return response()->json(['data' => $this->tyreReportService->costPerKm($this->filters($request))]);
    }

    public function replacements(Request $request): JsonResponse
    {
        $this->authorize('tyre_reports.view');

        return response()->json(['data' => $this->tyreReportService->replacements($this->filters($request))]);
    }

    public function retreadHistory(Request $request): JsonResponse
    {
        $this->authorize('tyre_reports.view');

        return response()->json(['data' => $this->tyreReportService->retreadHistory($this->filters($request))]);
    }

    public function vehicleWiseUsage(Request $request): JsonResponse
    {
        $this->authorize('tyre_reports.view');

        return response()->json(['data' => $this->tyreReportService->vehicleWiseUsage($this->filters($request))]);
    }

    public function expenseSummary(Request $request): JsonResponse
    {
        $this->authorize('tyre_reports.view');

        return response()->json(['data' => $this->tyreReportService->expenseSummary($this->filters($request))]);
    }

    /**
     * @return array{from?: string, to?: string, vehicle_id?: string}
     */
    private function filters(Request $request): array
    {
        return array_filter([
            'from' => $request->filled('from') ? (string) $request->input('from') : null,
            'to' => $request->filled('to') ? (string) $request->input('to') : null,
            'vehicle_id' => $request->filled('vehicle_id') ? (string) $request->input('vehicle_id') : null,
        ]);
    }
}
