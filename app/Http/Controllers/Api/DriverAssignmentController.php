<?php

namespace App\Http\Controllers\Api;

use App\DriverAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDriverAssignmentRequest;
use App\Http\Resources\DriverAssignmentResource;
use App\Modules\DriverManagement\Services\DriverAssignmentService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DriverAssignmentController extends Controller
{
    public function __construct(private readonly DriverAssignmentService $driverAssignmentService)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DriverAssignment::class);

        return DriverAssignmentResource::collection($this->driverAssignmentService->paginate());
    }

    public function store(StoreDriverAssignmentRequest $request): DriverAssignmentResource
    {
        $assignment = $this->driverAssignmentService->create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return new DriverAssignmentResource($assignment);
    }

    public function show(DriverAssignment $driverAssignment): DriverAssignmentResource
    {
        $this->authorize('view', $driverAssignment);

        return new DriverAssignmentResource($driverAssignment->load('driver'));
    }

    public function update(StoreDriverAssignmentRequest $request, DriverAssignment $driverAssignment): DriverAssignmentResource
    {
        $driverAssignment = $this->driverAssignmentService->update($driverAssignment, [
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return new DriverAssignmentResource($driverAssignment);
    }

    public function destroy(DriverAssignment $driverAssignment): Response
    {
        $this->authorize('delete', $driverAssignment);
        $this->driverAssignmentService->delete($driverAssignment);

        return response()->noContent();
    }
}
