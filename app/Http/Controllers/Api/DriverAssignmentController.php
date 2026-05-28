<?php

namespace App\Http\Controllers\Api;

use App\DriverAssignment;
use App\Http\Requests\StoreDriverAssignmentRequest;
use App\Http\Resources\DriverAssignmentResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DriverAssignmentController
{
    public function index(): AnonymousResourceCollection
    {
        return DriverAssignmentResource::collection(DriverAssignment::query()->latest()->paginate());
    }

    public function store(StoreDriverAssignmentRequest $request): DriverAssignmentResource
    {
        $assignment = DriverAssignment::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return new DriverAssignmentResource($assignment);
    }

    public function show(DriverAssignment $driverAssignment): DriverAssignmentResource
    {
        return new DriverAssignmentResource($driverAssignment);
    }

    public function update(StoreDriverAssignmentRequest $request, DriverAssignment $driverAssignment): DriverAssignmentResource
    {
        $driverAssignment->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return new DriverAssignmentResource($driverAssignment->fresh());
    }

    public function destroy(DriverAssignment $driverAssignment): Response
    {
        $driverAssignment->delete();

        return response()->noContent();
    }
}
