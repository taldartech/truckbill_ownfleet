<?php

namespace App\Http\Controllers\Api;

use App\Exports\TripReportExport;
use App\Http\Controllers\Controller;
use App\Modules\TripManagement\Services\FleetMonthlyReportService;
use App\Support\ReportPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FleetMonthlyReportController extends Controller
{
    public function __construct(private readonly FleetMonthlyReportService $fleetMonthlyReportService) {}

    public function show(Request $request): JsonResponse
    {
        $this->authorize('fleet_monthly_report.view');

        $month = $this->validatedMonth($request);
        $report = $this->fleetMonthlyReportService->build($month);

        return ReportPaginator::paginate($request, $report['rows'], [
            'summary' => $report['summary'],
            'company_name' => $report['company_name'],
            'month' => $report['month'],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('fleet_monthly_report.export');

        $month = $this->validatedMonth($request);
        $rows = $this->fleetMonthlyReportService->exportRows($month);
        $filename = 'fleet-monthly-report-'.$month.'.xlsx';

        return Excel::download(new TripReportExport($rows), $filename);
    }

    private function validatedMonth(Request $request): string
    {
        return (string) $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ])['month'];
    }
}
