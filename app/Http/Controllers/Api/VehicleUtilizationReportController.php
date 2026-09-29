<?php

namespace App\Http\Controllers\Api;

use App\Exports\TripReportExport;
use App\Http\Controllers\Controller;
use App\Modules\TripManagement\Services\VehicleUtilizationReportService;
use App\Support\ReportPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VehicleUtilizationReportController extends Controller
{
    public function __construct(
        private readonly VehicleUtilizationReportService $vehicleUtilizationReportService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorize('trip_reports.view');

        $filters = $this->validatedFilters($request);
        $report = $this->vehicleUtilizationReportService->build($filters);

        return ReportPaginator::paginate($request, $report['rows'], [
            'summary' => $report['summary'],
            'company_name' => $report['company_name'],
            'period' => $report['period'],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('trip_reports.export');

        $filters = $this->validatedFilters($request);
        $rows = $this->vehicleUtilizationReportService->exportRows($filters);
        $filename = 'vehicle-utilization-report-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new TripReportExport($rows), $filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'string', 'in:all,on_trip,available,idle,maintenance,inactive'],
            'vehicle_id' => ['nullable', 'uuid'],
            'min_idle_days' => ['nullable', 'integer', 'min:0'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
    }
}
