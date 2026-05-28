<?php

namespace App\Http\Controllers\Api;

use App\Attendance;
use App\Http\Requests\MarkAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceController
{
    public function index(): AnonymousResourceCollection
    {
        return AttendanceResource::collection(Attendance::query()->latest('attendance_date')->paginate());
    }

    public function store(MarkAttendanceRequest $request): AttendanceResource
    {
        $payload = $request->validated();
        $payload['wage_owner_driver_id'] = $payload['primary_driver_id'];
        $payload['created_by'] = $request->user()?->id;
        $payload['updated_by'] = $request->user()?->id;

        $attendance = Attendance::query()->updateOrCreate(
            [
                'vehicle_id' => $payload['vehicle_id'],
                'attendance_date' => $payload['attendance_date'],
            ],
            $payload
        );

        return new AttendanceResource($attendance);
    }

    /**
     * Display the specified resource.
     */
    public function show(Attendance $attendance): AttendanceResource
    {
        return new AttendanceResource($attendance);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(MarkAttendanceRequest $request, Attendance $attendance): AttendanceResource
    {
        $payload = $request->validated();
        $payload['wage_owner_driver_id'] = $payload['primary_driver_id'];
        $payload['updated_by'] = $request->user()?->id;
        $attendance->update($payload);

        return new AttendanceResource($attendance->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attendance $attendance): \Illuminate\Http\Response
    {
        $attendance->delete();

        return response()->noContent();
    }
}
