<?php

namespace App\Http\Controllers\Api;

use App\Attendance;
use App\AttendanceMonthLock;
use App\Http\Controllers\Controller;
use App\Http\Requests\CloseMonthlyWagesRequest;
use App\Http\Requests\LockAttendanceMonthRequest;
use App\Http\Requests\MarkAllMonthlyWagesPaidRequest;
use App\Http\Requests\MarkAttendanceRequest;
use App\Http\Requests\MarkMonthlyWagePaidRequest;
use App\Http\Requests\MarkWagePaidRequest;
use App\Http\Requests\ReopenAttendanceMonthRequest;
use App\Http\Requests\UpdateMonthlySettlementLineRequest;
use App\Http\Requests\UpdateVehicleSettlementLineRequest;
use App\Http\Resources\AttendanceMonthOverviewResource;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\MonthlyDriverWageResource;
use App\Modules\DriverManagement\Services\AttendanceService;
use App\Modules\DriverManagement\Services\MonthlySettlementService;
use App\MonthlyDriverWage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly MonthlySettlementService $monthlySettlementService,
    ) {}

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

    public function monthlyWages(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Attendance::class);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        return MonthlyDriverWageResource::collection(
            $this->attendanceService->listMonthlyWages($validated['month']),
        );
    }

    public function settlementSheet(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        return response()->json([
            'data' => $this->monthlySettlementService->sheet($validated['month']),
        ]);
    }

    public function updateSettlementLine(
        UpdateMonthlySettlementLineRequest $request,
        MonthlyDriverWage $monthlyDriverWage,
    ): MonthlyDriverWageResource {
        $monthlyDriverWage = $this->monthlySettlementService->updatePayrollLine(
            $monthlyDriverWage,
            $request->validated(),
            $request->user()?->id,
        );

        return new MonthlyDriverWageResource($monthlyDriverWage);
    }

    public function updateVehicleSettlementLine(UpdateVehicleSettlementLineRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();

        return response()->json([
            'data' => $this->monthlySettlementService->updateVehicleLine(
                $validated['month'],
                $validated,
            ),
        ]);
    }

    public function closeMonth(CloseMonthlyWagesRequest $request): AnonymousResourceCollection
    {
        return MonthlyDriverWageResource::collection(
            $this->attendanceService->closeMonth($request->string('month')->toString(), $request->user()?->id),
        );
    }

    public function lockMonth(LockAttendanceMonthRequest $request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'data' => $this->monthLockPayload(
                $this->attendanceService->lockMonth($request->string('month')->toString(), $request->user()?->id),
            ),
        ]);
    }

    public function reopenMonth(ReopenAttendanceMonthRequest $request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'data' => $this->monthLockPayload(
                $this->attendanceService->reopenMonth($request->string('month')->toString()),
            ),
        ]);
    }

    public function unlockMonth(ReopenAttendanceMonthRequest $request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'data' => $this->monthLockPayload(
                $this->attendanceService->unlockMonth($request->string('month')->toString()),
            ),
        ]);
    }

    public function markAllMonthlyWagesPaid(MarkAllMonthlyWagesPaidRequest $request): AnonymousResourceCollection
    {
        return MonthlyDriverWageResource::collection(
            $this->attendanceService->markAllMonthlyWagesPaid(
                $request->string('month')->toString(),
                $request->user()?->id,
                $request->input('payment_notes'),
            ),
        );
    }

    /**
     * @return array{status: string, closed_at: string|null, locked_at: string|null}
     */
    private function monthLockPayload(AttendanceMonthLock $lock): array
    {
        return [
            'status' => $lock->status->value,
            'closed_at' => $lock->closed_at?->toISOString(),
            'locked_at' => $lock->locked_at?->toISOString(),
        ];
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

    public function markMonthlyWagePaid(
        MarkMonthlyWagePaidRequest $request,
        MonthlyDriverWage $monthlyDriverWage
    ): MonthlyDriverWageResource {
        $monthlyDriverWage = $this->attendanceService->markMonthlyWagePaid($monthlyDriverWage, [
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return new MonthlyDriverWageResource($monthlyDriverWage);
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
            'status' => ['nullable', 'string', 'in:PRESENT,ABSENT,NO_WORK_DAY,present,absent,no_work_day'],
            'wage_payment_status' => ['nullable', 'string', 'in:paid,unpaid'],
        ]);

        if (! empty($validated['status'])) {
            $validated['status'] = strtoupper($validated['status']);
        }

        return $validated;
    }
}
