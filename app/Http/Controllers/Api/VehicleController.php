<?php

namespace App\Http\Controllers\Api;

use App\DriverAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\ToggleVehicleStatusRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Http\Resources\VehicleTripSelectionResource;
use App\Modules\DriverManagement\Services\DriverAssignmentService;
use App\Services\SubscriptionGuard;
use App\Support\CurrentTenant;
use App\Support\TyreLayoutDefaults;
use App\Tenant;
use App\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class VehicleController extends Controller
{
    public function __construct(
        private readonly DriverAssignmentService $driverAssignmentService,
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
            })
            ->latest();

        if (! $includeIneligible) {
            $query->where('is_active', true)
                ->whereHas('currentPrimaryAssignment', function ($assignmentQuery): void {
                    Vehicle::applyCurrentPrimaryAssignmentConstraints($assignmentQuery);
                })
                ->whereHas('currentPrimaryAssignment.driver', fn ($driverQuery) => $driverQuery->where('is_active', true))
                ->whereDoesntHave('trips', function ($tripQuery): void {
                    $tripQuery->whereIn('status', ['CREATED', 'IN_PROGRESS']);
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
        $this->subscriptionGuard->assertCanCreateVehicle($tenant);

        $vehicle = Vehicle::query()->create([
            ...$validated,
            'is_active' => true,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

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
        $vehicle->update([
            ...$request->validated(),
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

    public function destroy(Vehicle $vehicle): Response
    {
        $this->authorize('delete', $vehicle);
        $vehicle->delete();

        return response()->noContent();
    }
}
