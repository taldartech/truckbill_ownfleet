<?php

namespace App\Http\Controllers\Api;

use App\Attendance;
use App\Http\Controllers\Controller;
use App\Http\Requests\MarkAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Modules\DriverManagement\Services\AttendanceService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $attendanceService)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Attendance::class);

        return AttendanceResource::collection($this->attendanceService->paginate());
    }

    public function store(MarkAttendanceRequest $request): AttendanceResource
    {
        $payload = $request->validated();
        $payload['created_by'] = $request->user()?->id;
        $payload['updated_by'] = $request->user()?->id;

        $attendance = $this->attendanceService->mark($payload);

        return new AttendanceResource($attendance);
    }

    /**
     * Display the specified resource.
     */
    public function show(Attendance $attendance): AttendanceResource
    {
        $this->authorize('view', $attendance);

        return new AttendanceResource($attendance->load(['primaryDriver', 'temporaryDriver', 'wageOwnerDriver']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(MarkAttendanceRequest $request, Attendance $attendance): AttendanceResource
    {
        $payload = $request->validated();
        $payload['updated_by'] = $request->user()?->id;
        $attendance = $this->attendanceService->update($attendance, $payload);

        return new AttendanceResource($attendance);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attendance $attendance): \Illuminate\Http\Response
    {
        $this->authorize('delete', $attendance);
        $this->attendanceService->delete($attendance);

        return response()->noContent();
    }
}
