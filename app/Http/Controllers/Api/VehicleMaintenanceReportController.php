<?php

namespace App\Http\Controllers\Api;

use App\Exports\TripReportExport;
use App\Http\Controllers\Controller;
use App\Modules\VehicleMaintenanceManagement\Services\VehicleMaintenanceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('maintenance_reports.export');

        $type = $request->string('type', 'cost')->toString();
        $filters = $this->filters($request);

        $rows = match ($type) {
            'truck' => $this->reportService->truckWiseCost($filters),
            default => $this->reportService->costSummary($filters),
        };

        $filename = 'maintenance-report-'.$type.'-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new TripReportExport($this->exportRows($rows)), $filename);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function exportRows(array $rows): array
    {
        $hiddenColumns = ['service_type', 'vehicle_id', 'maintenance_service_type_id'];

        return array_map(
            fn (array $row): array => array_diff_key($row, array_flip($hiddenColumns)),
            $rows,
        );
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
