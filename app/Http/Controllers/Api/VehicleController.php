<?php

namespace App\Http\Controllers\Api;

use App\DriverAssignment;
use App\Exports\VehiclesTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteVehicleRequest;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\ToggleVehicleStatusRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Http\Resources\VehicleTripSelectionResource;
use App\Imports\VehiclesBulkImport;
use App\Modules\DriverManagement\Services\DriverAssignmentService;
use App\Modules\TripManagement\Services\VehicleTripEligibilityService;
use App\Services\SubscriptionGuard;
use App\Support\CurrentTenant;
use App\Support\TyreLayoutDefaults;
use App\Tenant;
use App\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class VehicleController extends Controller
{
    public function __construct(
        private readonly DriverAssignmentService $driverAssignmentService,
        private readonly VehicleTripEligibilityService $vehicleTripEligibility,
        private readonly SubscriptionGuard $subscriptionGuard,
    ) {}

    public function forTripSelection(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Vehicle::class);

        $search = trim($request->string('search')->toString());
        $perPage = min(max((int) $request->input('per_page', 20), 1), 50);
        $includeIneligible = $request->boolean('include_ineligible', true);

        if (mb_strlen($search) < 2) {
            return VehicleTripSelectionResource::collection(
                new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage),
            );
        }

        $query = Vehicle::query()
            ->with(['currentPrimaryAssignment.driver'])
            ->where(function ($builder) use ($search): void {
                $builder->where('registration_number', 'like', "%{$search}%")
                    ->orWhere('make', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhereHas('currentPrimaryAssignment.driver', function ($driverQuery) use ($search): void {
                        $driverQuery->where('name', 'like', "%{$search}%");
                    });
            });

        $this->vehicleTripEligibility->applySelectableFirstOrdering(
            $query,
            $request->filled('as_of_date') ? $request->string('as_of_date')->toString() : null,
            $request->filled('end_at') ? $request->string('end_at')->toString() : null,
        );

        if (! $includeIneligible) {
            $query->where('is_active', true)
                ->whereHas('currentPrimaryAssignment', function ($assignmentQuery): void {
                    Vehicle::applyCurrentPrimaryAssignmentConstraints($assignmentQuery);
                })
                ->whereHas('currentPrimaryAssignment.driver', fn ($driverQuery) => $driverQuery->where('is_active', true))
                ->whereDoesntHave('trips', function ($tripQuery): void {
                    $tripQuery->whereIn('status', ['PENDING', 'IN_PROGRESS']);
                });
        }

        return VehicleTripSelectionResource::collection($query->paginate($perPage));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Vehicle::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);
        $query = Vehicle::query()
            ->with([
                'currentPrimaryAssignment.driver',
                'createdBy:id,name',
                'updatedBy:id,name',
            ])
            ->latest();

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();

            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        } elseif ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->filled('vehicle_type')) {
            $query->where('vehicle_type', $request->string('vehicle_type')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($builder) use ($search) {
                $builder->where('registration_number', 'like', "%{$search}%")
                    ->orWhere('make', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        if ($request->filled('primary_driver')) {
            $query->filterByPrimaryDriver($request->string('primary_driver')->toString());
        }

        return VehicleResource::collection($query->paginate($perPage));
    }

    public function store(StoreVehicleRequest $request): VehicleResource
    {
        $validated = $request->validated();
        $validated['tyre_layout'] = $validated['tyre_layout'] ?? TyreLayoutDefaults::defaultCode();

        $tenant = Tenant::query()->with('subscription')->findOrFail(CurrentTenant::requireId());

        $vehicle = Vehicle::withTrashed()
            ->where('tenant_id', CurrentTenant::id())
            ->where('registration_number', $validated['registration_number'])
            ->first();

        if ($vehicle) {
            if ($vehicle->trashed()) {
                $this->subscriptionGuard->assertCanCreateVehicle($tenant);
            }

            $vehicle->restore();
            $vehicle->update([
                ...$validated,
                'is_active' => true,
                'updated_by' => $request->user()?->id,
            ]);
            $vehicle->wasRecentlyCreated = true;
        } else {
            $this->subscriptionGuard->assertCanCreateVehicle($tenant);

            $vehicle = Vehicle::query()->create([
                ...$validated,
                'is_active' => true,
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);
        }

        return new VehicleResource($vehicle->load([
            'createdBy:id,name',
            'updatedBy:id,name',
        ]));
    }

    public function show(Vehicle $vehicle): VehicleResource
    {
        $this->authorize('view', $vehicle);

        return new VehicleResource($vehicle->load([
            'documents',
            'currentPrimaryAssignment.driver',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]));
    }

    public function primaryAssignment(Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        return response()->json(
            $this->driverAssignmentService->primaryAssignmentSummary($vehicle->id),
        );
    }

    public function removePrimaryDriver(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);
        $this->authorize('create', DriverAssignment::class);

        $this->driverAssignmentService->removePrimaryDriver(
            $vehicle->id,
            $request->user()?->id,
        );

        return response()->json([
            'message' => 'Primary driver removed from this truck.',
            'vehicle_id' => $vehicle->id,
            ...$this->driverAssignmentService->primaryAssignmentSummary($vehicle->id),
        ]);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): VehicleResource
    {
        $validated = $request->validated();

        if (array_key_exists('odometer_km', $validated) && $validated['odometer_km'] !== null) {
            $newReading = (float) $validated['odometer_km'];
            $currentReading = max(0, (float) ($vehicle->odometer_km ?? 0));

            if ($newReading < $currentReading) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'odometer_km' => "Truck running meter reading cannot be less than the last recorded reading ({$currentReading} km).",
                ]);
            }
        }

        $vehicle->update([
            ...$validated,
            'updated_by' => $request->user()?->id,
        ]);

        return new VehicleResource($vehicle->fresh([
            'createdBy:id,name',
            'updatedBy:id,name',
        ]));
    }

    public function toggleStatus(ToggleVehicleStatusRequest $request, Vehicle $vehicle): VehicleResource
    {
        $vehicle->update([
            'is_active' => (bool) $request->validated('is_active'),
            'updated_by' => $request->user()?->id,
        ]);

        return new VehicleResource($vehicle->fresh());
    }

    public function destroy(Request $request, Vehicle $vehicle): Response
    {
        $this->authorize('delete', $vehicle);
    
        $validated = $request->validate([
            'deleted_reason' => ['required', 'string', 'max:1000'],
        ]);
    
        $vehicle->update([
            'deleted_by' => $request->user()?->id,
            'deleted_reason' => $validated['deleted_reason'],
        ]);
    
        $vehicle->delete();
    
        return response()->noContent();
    }

    public function downloadBulkTemplate()
    {
        $this->authorize('create', Vehicle::class);
    
        return Excel::download(
            new VehiclesTemplateExport(),
            'vehicles_template.xlsx'
        );
    }

    public function bulkUpload(Request $request): JsonResponse
    {
        $this->authorize('create', Vehicle::class);
    
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:10240',
            ],
        ]);
    
        $import = new VehiclesBulkImport();
    
        Excel::import($import, $request->file('file'));
    
        return response()->json([
            'message' => 'Bulk upload completed.',
            ...$import->result(),
        ]);
    }
}
