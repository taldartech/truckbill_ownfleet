<?php

namespace App\Http\Controllers\Api;

use App\Exports\TripReportExport;
use App\Http\Controllers\Controller;
use App\Modules\TyreManagement\Services\TyreReportService;
use App\Support\ReportPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TyreReportController extends Controller
{
    public function __construct(private readonly TyreReportService $tyreReportService) {}

    public function tyreLife(Request $request): JsonResponse
    {
        $this->authorize('tyre_reports.view');

        return ReportPaginator::paginate(
            $request,
            $this->tyreReportService->tyreLife($this->filters($request)),
        );
    }

    public function costPerKm(Request $request): JsonResponse
    {
        $this->authorize('tyre_reports.view');

        return ReportPaginator::paginate(
            $request,
            $this->tyreReportService->costPerKm($this->filters($request)),
        );
    }

    public function replacements(Request $request): JsonResponse
    {
        $this->authorize('tyre_reports.view');

        return ReportPaginator::paginate(
            $request,
            $this->tyreReportService->replacements($this->filters($request)),
        );
    }

    public function vehicleWiseUsage(Request $request): JsonResponse
    {
        $this->authorize('tyre_reports.view');

        return ReportPaginator::paginate(
            $request,
            $this->tyreReportService->vehicleWiseUsage($this->filters($request)),
        );
    }

    public function expenseSummary(Request $request): JsonResponse
    {
        $this->authorize('tyre_reports.view');

        return ReportPaginator::paginate(
            $request,
            $this->expenseSummaryRows($this->filters($request)),
        );
    }

    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('tyre_reports.export');

        $type = $request->string('type', 'life')->toString();
        $filters = $this->filters($request);

        $rows = match ($type) {
            'cost' => $this->tyreReportService->costPerKm($filters),
            'replacements' => $this->tyreReportService->replacements($filters),
            'vehicle' => $this->tyreReportService->vehicleWiseUsage($filters),
            'expenses' => $this->expenseSummaryRows($filters),
            default => $this->tyreReportService->tyreLife($filters),
        };

        $filename = 'tyre-report-'.$type.'-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new TripReportExport($rows), $filename);
    }

    /**
     * @param  array{from?: string, to?: string, vehicle_id?: string}  $filters
     * @return list<array{expense_type: string, total: float}>
     */
    private function expenseSummaryRows(array $filters): array
    {
        return collect($this->tyreReportService->expenseSummary($filters))
            ->map(fn (float $total, string $expenseType): array => [
                'expense_type' => $expenseType,
                'total' => $total,
            ])
            ->values()
            ->all();
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
