<?php

namespace App\Http\Controllers\Api;

use App\DriverAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDriverAssignmentRequest;
use App\Http\Requests\UpdateDriverAssignmentRequest;
use App\Http\Resources\DriverAssignmentResource;
use App\Modules\DriverManagement\Services\DriverAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DriverAssignmentController extends Controller
{
    public function __construct(private readonly DriverAssignmentService $driverAssignmentService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DriverAssignment::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);
        $vehicleId = $request->filled('vehicle_id') ? $request->string('vehicle_id')->toString() : null;

        return DriverAssignmentResource::collection(
            $this->driverAssignmentService->paginate($perPage, $vehicleId),
        );
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

    public function update(UpdateDriverAssignmentRequest $request, DriverAssignment $driverAssignment): DriverAssignmentResource
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
