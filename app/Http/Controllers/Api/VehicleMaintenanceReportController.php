<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\VehicleMaintenanceManagement\Services\VehicleMaintenanceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleMaintenanceReportController extends Controller
{
    public function __construct(private readonly VehicleMaintenanceReportService $reportService) {}

    public function costSummary(Request $request): JsonResponse
    {
        $this->authorize('maintenance_reports.view');

        return response()->json([
            'data' => $this->reportService->costSummary($this->filters($request)),
        ]);
    }

    public function truckWise(Request $request): JsonResponse
    {
        $this->authorize('maintenance_reports.view');

        return response()->json([
            'data' => $this->reportService->truckWiseCost($this->filters($request)),
        ]);
    }

    public function dueServices(): JsonResponse
    {
        $this->authorize('maintenance_reports.view');

        return response()->json([
            'data' => $this->reportService->dueServices(),
        ]);
    }

    /**
     * @return array{from?: string, to?: string, maintenance_service_type_id?: int, vehicle_id?: string}
     */
    private function filters(Request $request): array
    {
        return array_filter([
            'from' => $request->filled('from') ? (string) $request->input('from') : null,
            'to' => $request->filled('to') ? (string) $request->input('to') : null,
            'maintenance_service_type_id' => $request->filled('maintenance_service_type_id')
                ? (int) $request->input('maintenance_service_type_id')
                : null,
            'vehicle_id' => $request->filled('vehicle_id') ? (string) $request->input('vehicle_id') : null,
        ]);
    }
}
