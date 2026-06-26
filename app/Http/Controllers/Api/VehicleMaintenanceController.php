<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleMaintenanceRequest;
use App\Http\Requests\UpdateVehicleMaintenanceRequest;
use App\Http\Resources\VehicleMaintenanceResource;
use App\Modules\VehicleMaintenanceManagement\Services\VehicleMaintenanceService;
use App\Vehicle;
use App\VehicleMaintenance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class VehicleMaintenanceController extends Controller
{
    public function __construct(private readonly VehicleMaintenanceService $maintenanceService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VehicleMaintenance::class);

        $perPage = min(max((int) $request->input('per_page', 25), 1), 500);

        $records = $this->maintenanceService->paginate([
            'search' => $request->filled('search') ? $request->string('search')->toString() : null,
            'vehicle_id' => $request->filled('vehicle_id') ? $request->string('vehicle_id')->toString() : null,
            'maintenance_service_type_id' => $request->filled('maintenance_service_type_id')
                ? (int) $request->input('maintenance_service_type_id')
                : ($request->filled('service_type_id') ? (int) $request->input('service_type_id') : null),
            'from' => $request->filled('from') ? $request->string('from')->toString() : null,
            'to' => $request->filled('to') ? $request->string('to')->toString() : null,
        ], $perPage);

        return VehicleMaintenanceResource::collection($records);
    }

    public function due(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VehicleMaintenance::class);

        return VehicleMaintenanceResource::collection($this->maintenanceService->dueServices());
    }

    public function forVehicle(Request $request, Vehicle $vehicle): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VehicleMaintenance::class);

        $perPage = min(max((int) $request->input('per_page', 25), 1), 500);

        return VehicleMaintenanceResource::collection(
            $this->maintenanceService->listForVehicle($vehicle->id, $perPage),
        );
    }

    public function store(StoreVehicleMaintenanceRequest $request): VehicleMaintenanceResource
    {
        $validated = $request->validated();
        $validated['cost_amount'] = $validated['cost_amount'] ?? 0;
        $validated['created_by'] = $request->user()?->id;
        $validated['updated_by'] = $request->user()?->id;

        $maintenance = $this->maintenanceService->create($validated);

        return new VehicleMaintenanceResource($maintenance);
    }

    public function show(VehicleMaintenance $vehicleMaintenance): VehicleMaintenanceResource
    {
        $this->authorize('view', $vehicleMaintenance);
        $vehicleMaintenance->load([
            'vehicle:id,registration_number,is_active',
            'maintenanceServiceType:id,name,code',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);

        return new VehicleMaintenanceResource($vehicleMaintenance);
    }

    public function update(UpdateVehicleMaintenanceRequest $request, VehicleMaintenance $vehicleMaintenance): VehicleMaintenanceResource
    {
        $validated = $request->validated();

        if (array_key_exists('cost_amount', $validated) && $validated['cost_amount'] === null) {
            $validated['cost_amount'] = 0;
        }

        $validated['updated_by'] = $request->user()?->id;

        $maintenance = $this->maintenanceService->update($vehicleMaintenance, $validated);

        return new VehicleMaintenanceResource($maintenance);
    }

    public function destroy(VehicleMaintenance $vehicleMaintenance): Response
    {
        $this->authorize('delete', $vehicleMaintenance);
        $this->maintenanceService->delete($vehicleMaintenance);

        return response()->noContent();
    }
}
