<?php

namespace App\Http\Controllers\Api;

use App\Exports\TripReportExport;
use App\Http\Controllers\Controller;
use App\Modules\TripManagement\Services\TripReportService;
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

        return response()->json([
            'data' => $this->tripReportService->profitability($this->filters($request)),
        ]);
    }

    public function vehicleWise(Request $request): JsonResponse
    {
        $this->authorize('trip_reports.view');

        return response()->json([
            'data' => $this->tripReportService->vehicleWise($this->filters($request)),
        ]);
    }

    public function driverWise(Request $request): JsonResponse
    {
        $this->authorize('trip_reports.view');

        return response()->json([
            'data' => $this->tripReportService->driverWise($this->filters($request)),
        ]);
    }

    public function emptyKm(Request $request): JsonResponse
    {
        $this->authorize('trip_reports.view');

        return response()->json([
            'data' => $this->tripReportService->emptyKm($this->filters($request)),
        ]);
    }

    public function expenseSummary(Request $request): JsonResponse
    {
        $this->authorize('trip_reports.view');

        return response()->json([
            'data' => $this->tripReportService->expenseSummary($this->filters($request)),
        ]);
    }

    public function routeProfitability(Request $request): JsonResponse
    {
        $this->authorize('trip_reports.view');

        return response()->json([
            'data' => $this->tripReportService->routeProfitability($this->filters($request)),
        ]);
    }

    public function pendingFreight(Request $request): JsonResponse
    {
        $this->authorize('trip_reports.view');

        return response()->json([
            'data' => $this->tripReportService->pendingFreightCollection($this->filters($request)),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('trip_reports.export');

        $type = $request->string('type', 'profitability')->toString();
        $filters = $this->filters($request);

        $rows = match ($type) {
            'vehicle-wise' => $this->tripReportService->vehicleWise($filters),
            'driver-wise' => $this->tripReportService->driverWise($filters),
            'empty-km' => $this->tripReportService->emptyKm($filters),
            'expense-summary' => $this->tripReportService->expenseSummary($filters),
            'route-profitability' => $this->tripReportService->routeProfitability($filters),
            'pending-freight' => $this->tripReportService->pendingFreightCollection($filters),
            default => $this->tripReportService->profitability($filters),
        };

        $filename = 'trip-report-'.$type.'-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new TripReportExport($rows), $filename);
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
        ]);
    }
}
