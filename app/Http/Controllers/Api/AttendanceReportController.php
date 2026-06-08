<?php

namespace App\Http\Controllers\Api;

use App\Attendance;
use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('reports.view');

        $perPage = min(max((int) $request->input('per_page', 50), 1), 500);

        return AttendanceResource::collection(
            $this->filteredQuery($request)
                ->latest('attendance_date')
                ->paginate($perPage),
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('reports.export');

        $filename = 'attendance-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Date',
                'Truck',
                'Primary Driver',
                'Temporary Driver',
                'Status',
                'Wage Amount',
                'Wage Payment Status',
                'Marked Via',
                'Temporary Remarks',
                'Wage Transfer Notes',
            ]);

            $this->filteredQuery($request)
                ->latest('attendance_date')
                ->limit(10000)
                ->get()
                ->each(function (Attendance $attendance) use ($handle): void {
                    fputcsv($handle, [
                        $attendance->attendance_date?->toDateString(),
                        $attendance->vehicle?->registration_number,
                        $attendance->primaryDriver?->name,
                        $attendance->temporaryDriver?->name,
                        $attendance->status,
                        $attendance->wage_amount,
                        $attendance->wage_payment_status?->value ?? $attendance->wage_payment_status,
                        $attendance->marked_via?->value ?? $attendance->marked_via,
                        $attendance->temporary_assignment_remarks,
                        $attendance->wage_transfer_notes,
                    ]);
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Attendance>
     */
    private function filteredQuery(Request $request)
    {
        $query = Attendance::query()
            ->with(['vehicle', 'primaryDriver', 'temporaryDriver', 'wageOwnerDriver']);

        if ($request->filled('from_date')) {
            $query->whereDate('attendance_date', '>=', $request->string('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('attendance_date', '<=', $request->string('to_date'));
        }

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->string('vehicle_id'));
        }

        if ($request->filled('driver_id')) {
            $driverId = $request->string('driver_id');
            $query->where(function ($builder) use ($driverId): void {
                $builder
                    ->where('primary_driver_id', $driverId)
                    ->orWhere('temporary_driver_id', $driverId)
                    ->orWhere('wage_owner_driver_id', $driverId);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->string('status')));
        }

        return $query;
    }
}
