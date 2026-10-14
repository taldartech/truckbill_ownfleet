<?php

namespace App\Http\Controllers\Api;

use App\Exports\TripReportExport;
use App\Http\Controllers\Controller;
use App\Modules\TripManagement\Services\TripReportService;
use App\Support\ReportPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TripReportController extends Controller
{
    public function __construct(private readonly TripReportService $tripReportService) {}

    public function profitability(Request $request): JsonResponse
    {
        $this->authorize('trip_reports.view');

        return $this->reportResponse($request, fn (array $filters): array => $this->tripReportService->profitability($filters));
    }

    public function vehicleWise(Request $request): JsonResponse
    {
        $this->authorize('trip_reports.view');

        return $this->reportResponse($request, fn (array $filters): array => $this->tripReportService->vehicleWise($filters));
    }

    public function driverWise(Request $request): JsonResponse
    {
        $this->authorize('trip_reports.view');

        return $this->reportResponse($request, fn (array $filters): array => $this->tripReportService->driverWise($filters));
    }

    public function expenseSummary(Request $request): JsonResponse
    {
        $this->authorize('trip_reports.view');
        $filters = $this->filters($request);
        $rows = $this->tripReportService->expenseSummary($filters);

        return ReportPaginator::paginate($request, $rows, [
            'summary' => array_merge($this->tripReportService->financialSummary($filters), [
                'trip_count' => count(array_unique(array_filter(array_column($rows, 'trip_id')))),
                'total_expense_amount' => round((float) array_sum(array_column($rows, 'amount')), 2),
            ]),
        ]);
    }

    public function routeProfitability(Request $request): JsonResponse
    {
        $this->authorize('trip_reports.view');

        return $this->reportResponse($request, fn (array $filters): array => $this->tripReportService->routeProfitability($filters));
    }

    public function fuelPerformance(Request $request): JsonResponse
    {
        $this->authorize('trip_reports.view');
        $filters = $this->filters($request);

        $rows = $this->tripReportService->fuelPerformance($filters);
        $summary = $this->tripReportService->fuelPerformanceSummary($filters);

        return ReportPaginator::paginate($request, $rows, [
            'summary' => $summary,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('trip_reports.export');

        $type = $request->string('type', 'profitability')->toString();
        $filters = $this->filters($request);

        $rows = match ($type) {
            'fuel-performance' => $this->tripReportService->fuelPerformance($filters),
            'vehicle-wise' => $this->tripReportService->vehicleWise($filters),
            'driver-wise' => $this->tripReportService->driverWise($filters),
            'expense-summary' => $this->tripReportService->expenseSummary($filters),
            'route-profitability' => $this->tripReportService->routeProfitability($filters),
            default => $this->tripReportService->profitability($filters),
        };

        $filename = 'trip-report-'.$type.'-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new TripReportExport($rows), $filename);
    }

    /**
     * @param  callable(array<string, mixed>): list<array<string, mixed>>  $report
     */
    private function reportResponse(Request $request, callable $report): JsonResponse
    {
        $filters = $this->filters($request);

        return ReportPaginator::paginate($request, $report($filters), [
            'summary' => $this->tripReportService->financialSummary($filters),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->only([
            'vehicle_id',
            'driver_id',
            'status',
            'from_date',
            'to_date',
            'customer_name',
            'expense_type',
            'dimension',
        ]);
    }
}
