<?php

namespace App\Http\Controllers\Api;

use App\Attendance;
use App\Http\Controllers\Controller;
use App\Http\Requests\MarkAttendanceRequest;
use App\Http\Requests\MarkWagePaidRequest;
use App\Http\Resources\AttendanceMonthOverviewResource;
use App\Http\Resources\AttendanceResource;
use App\Modules\DriverManagement\Services\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Attendance::class);

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);

        return AttendanceResource::collection(
            $this->attendanceService->paginate($perPage, $this->listFilters($request)),
        );
    }

    public function monthOverview(Request $request): AttendanceMonthOverviewResource
    {
        $this->authorize('viewAny', Attendance::class);

        $validated = $request->validate([
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
        ]);

        return new AttendanceMonthOverviewResource(
            $this->attendanceService->monthOverview($validated['from_date'], $validated['to_date']),
        );
    }

    public function store(MarkAttendanceRequest $request): AttendanceResource
    {
        $payload = $request->validated();
        $payload['created_by'] = $request->user()?->id;
        $payload['updated_by'] = $request->user()?->id;

        $attendance = $this->attendanceService->mark($payload);

        return new AttendanceResource($attendance->load(['vehicle', 'primaryDriver', 'temporaryDriver', 'wageOwnerDriver']));
    }

    public function show(Attendance $attendance): AttendanceResource
    {
        $this->authorize('view', $attendance);

        return new AttendanceResource($attendance->load(['vehicle', 'primaryDriver', 'temporaryDriver', 'wageOwnerDriver']));
    }

    public function update(MarkAttendanceRequest $request, Attendance $attendance): AttendanceResource
    {
        $payload = $request->validated();
        $payload['updated_by'] = $request->user()?->id;
        $attendance = $this->attendanceService->update($attendance, $payload);

        return new AttendanceResource($attendance);
    }

    public function markWagePaid(MarkWagePaidRequest $request, Attendance $attendance): AttendanceResource
    {
        $attendance = $this->attendanceService->markWagePaid($attendance, [
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return new AttendanceResource($attendance);
    }

    public function destroy(Attendance $attendance): Response
    {
        $this->authorize('delete', $attendance);
        $this->attendanceService->delete($attendance);

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function listFilters(Request $request): array
    {
        $validated = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'vehicle_id' => ['nullable', 'uuid'],
            'primary_driver_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'string', 'in:PRESENT,ABSENT,present,absent'],
            'wage_payment_status' => ['nullable', 'string', 'in:paid,unpaid'],
        ]);

        if (! empty($validated['status'])) {
            $validated['status'] = strtoupper($validated['status']);
        }

        return $validated;
    }
}
