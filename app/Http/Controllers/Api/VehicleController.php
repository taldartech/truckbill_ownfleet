<?php

namespace App\Http\Controllers\Api;

use App\DriverAssignment;
use App\Enums\TripExpenseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\ToggleVehicleStatusRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Http\Resources\VehicleTripSelectionResource;
use App\Modules\DriverManagement\Services\DriverAssignmentService;
use App\Modules\TripManagement\Services\VehicleTripEligibilityService;
use App\Services\SubscriptionGuard;
use App\Support\CurrentTenant;
use App\Support\TyreLayoutDefaults;
use App\Tenant;
use App\Trip;
use App\TripExpense;
use App\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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

    public function fuelHistory(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('view', $vehicle);

        $perPage = min(max((int) $request->input('per_page', 10), 1), 100);

        $expensesQuery = TripExpense::query()
            ->where('trip_expenses.expense_type', TripExpenseType::Diesel->value)
            ->whereHas('trip', function ($query) use ($vehicle): void {
                $query->where('vehicle_id', $vehicle->id);
            });

        $totalFuelLiters = (float) (clone $expensesQuery)->sum('fuel_liters');
        $totalFuelCost = (float) (clone $expensesQuery)->sum('amount');

        $tripIds = (clone $expensesQuery)->pluck('trip_id')->unique();
        $totalDistanceKm = (float) Trip::query()
            ->whereIn('id', $tripIds)
            ->sum('total_distance_km');

        if ($totalDistanceKm <= 0) {
            $totalDistanceKm = (float) Trip::query()
                ->where('vehicle_id', $vehicle->id)
                ->sum('total_distance_km');
        }

        $averageMileage = $totalFuelLiters > 0
            ? round($totalDistanceKm / $totalFuelLiters, 2)
            : 0.0;

        $costPerKm = $totalDistanceKm > 0
            ? round($totalFuelCost / $totalDistanceKm, 2)
            : 0.0;

        $targetMileage = (float) ($vehicle->default_mileage_km_per_liter ?? 0);
        $expectedFuelLiters = ($targetMileage > 0 && $totalDistanceKm > 0)
            ? round($totalDistanceKm / $targetMileage, 2)
            : 0.0;

        $fuelVarianceLiters = $expectedFuelLiters > 0
            ? round($totalFuelLiters - $expectedFuelLiters, 2)
            : 0.0;

        $fuelVariancePercentage = $expectedFuelLiters > 0
            ? round(($fuelVarianceLiters / $expectedFuelLiters) * 100, 2)
            : 0.0;

        $paginator = (clone $expensesQuery)
            ->with([
                'trip:id,trip_number,vehicle_id,driver_id,substitute_driver_id,total_distance_km,planned_mileage_km_per_liter',
                'trip.driver:id,name,phone',
                'trip.substituteDriver:id,name,phone',
            ])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        $items = collect($paginator->items())->map(function (TripExpense $expense): array {
            $trip = $expense->trip;
            $driverName = $trip?->substituteDriver?->name ?? $trip?->driver?->name ?? '—';

            return [
                'id' => $expense->id,
                'expense_date' => $expense->expense_date?->toDateString(),
                'trip_id' => $expense->trip_id,
                'trip_number' => $trip?->trip_number,
                'driver_name' => $driverName,
                'fuel_odometer_km' => $expense->fuel_odometer_km !== null ? (float) $expense->fuel_odometer_km : null,
                'fuel_liters' => $expense->fuel_liters !== null ? (float) $expense->fuel_liters : null,
                'fuel_rate' => $expense->fuel_rate !== null ? (float) $expense->fuel_rate : null,
                'amount' => (float) $expense->amount,
                'notes' => $expense->notes,
                'trip_total_distance_km' => $trip?->total_distance_km !== null ? (float) $trip->total_distance_km : null,
                'trip_planned_mileage' => $trip?->planned_mileage_km_per_liter !== null ? (float) $trip->planned_mileage_km_per_liter : null,
            ];
        });

        return response()->json([
            'summary' => [
                'total_fuel_liters' => round($totalFuelLiters, 2),
                'total_fuel_cost' => round($totalFuelCost, 2),
                'total_distance_km' => round($totalDistanceKm, 2),
                'average_mileage' => $averageMileage,
                'cost_per_km' => $costPerKm,
                'target_mileage' => $targetMileage > 0 ? round($targetMileage, 2) : null,
                'expected_fuel_liters' => $expectedFuelLiters > 0 ? $expectedFuelLiters : null,
                'fuel_variance_liters' => $fuelVarianceLiters,
                'fuel_variance_percentage' => $fuelVariancePercentage,
            ],
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function destroy(Vehicle $vehicle): Response
    {
        $this->authorize('delete', $vehicle);
        $vehicle->delete();

        return response()->noContent();
    }
}
