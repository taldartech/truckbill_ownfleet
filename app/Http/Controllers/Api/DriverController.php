<?php

namespace App\Http\Controllers\Api;

use App\Driver;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDriverRequest;
use App\Http\Requests\UpdateDriverRequest;
use App\Http\Resources\DriverResource;
use App\Modules\DriverManagement\Services\DriverService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DriverController extends Controller
{
    public function __construct(private readonly DriverService $driverService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Driver::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);
        $activeOnly = $request->boolean('active_only', false);
        $search = $request->filled('search') ? $request->string('search')->toString() : null;
        $availableOnDate = $request->filled('available_on_date') ? $request->string('available_on_date')->toString() : null;
        $excludeVehicleId = $request->filled('exclude_vehicle_id') ? $request->string('exclude_vehicle_id')->toString() : null;
        $excludeAssignmentId = $request->filled('exclude_assignment_id') ? $request->string('exclude_assignment_id')->toString() : null;

        $drivers = $this->driverService->paginate($perPage, $activeOnly, $search, $availableOnDate, $excludeVehicleId, $excludeAssignmentId);
        $drivers->load(['currentPrimaryAssignment.vehicle', 'createdBy', 'updatedBy']);

        return DriverResource::collection($drivers);
    }

    public function store(StoreDriverRequest $request): DriverResource
    {
        $driver = $this->driverService->create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $driver->load(['currentPrimaryAssignment.vehicle', 'createdBy', 'updatedBy']);

        return new DriverResource($driver);
    }

    public function show(Driver $driver): DriverResource
    {
        $this->authorize('view', $driver);

        $driver->load(['currentPrimaryAssignment.vehicle', 'createdBy', 'updatedBy']);

        return new DriverResource($driver);
    }

    public function update(UpdateDriverRequest $request, Driver $driver): DriverResource
    {
        $driver = $this->driverService->update($driver, [
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        $driver->load(['currentPrimaryAssignment.vehicle', 'createdBy', 'updatedBy']);

        return new DriverResource($driver);
    }

    public function destroy(Request $request, Driver $driver): Response
    {
        $this->authorize('delete', $driver);
    
        $validated = $request->validate([
            'deleted_reason' => ['required', 'string', 'max:1000'],
        ]);
    
        $this->driverService->delete(
            $driver,
            $request->user()?->id,
            $validated['deleted_reason'],
        );
    
        return response()->noContent();
    }

    public function licenseExpiring(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Driver::class);

        $days = (int) $request->integer('days', 30);
        $days = max(1, min($days, 365));

        return DriverResource::collection($this->driverService->licenseExpiringWithinDays($days));
    }
}
