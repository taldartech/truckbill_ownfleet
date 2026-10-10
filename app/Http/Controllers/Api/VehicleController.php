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
use App\Services\BulkUploadService;
use App\Services\SubscriptionGuard;
use App\Support\CurrentTenant;
use App\Support\TyreLayoutDefaults;
use App\Support\VehicleRegistrationNumber;
use App\Tenant;
use App\Trip;
use App\TripExpense;
use App\TruckType;
use App\TyreLayout;
use App\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleController extends Controller
{
    public function __construct(
        private readonly DriverAssignmentService $driverAssignmentService,
        private readonly VehicleTripEligibilityService $vehicleTripEligibility,
        private readonly SubscriptionGuard $subscriptionGuard,
    ) {}

    public function bulkUploadTemplate(BulkUploadService $bulkUploadService): StreamedResponse
    {
        $this->authorize('viewAny', Vehicle::class);

        return $bulkUploadService->downloadTemplate(
            headers: [
                'Vehicle Number',
                'Vehicle Type',
                'Make',
                'Model',
                'Manufacture Year',
                'Odometer KM',
                'Mileage KM/L',
                'Max Load Ton',
                'Fuel Tank Capacity Ltr',
                'Tyre Layout',
            ],
            fileName: 'vehicles_bulk_upload_template.csv'
        );
    }

    public function bulkUpload(Request $request, BulkUploadService $bulkUploadService): JsonResponse
    {
        $this->authorize('create', Vehicle::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'extensions:csv,xlsx,xls', 'max:5120'],
        ], [
            'file.mimes' => 'The uploaded file must be a CSV or Excel file (.csv, .xlsx, .xls).',
            'file.extensions' => 'The uploaded file must be a CSV or Excel file (.csv, .xlsx, .xls).',
        ]);

        $tenant = Tenant::query()->with('subscription')->findOrFail(CurrentTenant::requireId());
        $tenantId = $tenant->id;
        $userId = $request->user()?->id;

        $truckTypesByName = [];
        $truckTypesByCode = [];
        foreach (TruckType::query()->where('tenant_id', $tenantId)->where('is_active', true)->get() as $tt) {
            $truckTypesByName[mb_strtolower($tt->name, 'UTF-8')] = $tt->code;
            $truckTypesByCode[mb_strtolower($tt->code, 'UTF-8')] = $tt->code;
        }

        $tyreLayoutsByName = [];
        $tyreLayoutsByCode = [];
        foreach (TyreLayout::query()->where('tenant_id', $tenantId)->where('is_active', true)->get() as $tl) {
            $tyreLayoutsByName[mb_strtolower($tl->name, 'UTF-8')] = $tl->code;
            $tyreLayoutsByCode[mb_strtolower($tl->code, 'UTF-8')] = $tl->code;
        }

        $rowValidator = function (array &$rowData, int $rowNumber) use (
            $truckTypesByName,
            $truckTypesByCode,
            $tyreLayoutsByName,
            $tyreLayoutsByCode
        ): array {
            $errors = [];

            // 1. Normalize registration number
            $regRaw = trim((string) ($rowData['registration_number'] ?? ''));
            $normalizedReg = VehicleRegistrationNumber::normalize($regRaw) ?? '';
            if ($normalizedReg === '') {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'Vehicle Number',
                    'value' => $regRaw,
                    'message' => 'Please provide a valid vehicle registration number.',
                ];
            } else {
                $rowData['registration_number'] = $normalizedReg;
            }

            // 2. Resolve vehicle type
            $typeRaw = trim((string) ($rowData['vehicle_type'] ?? ''));
            $typeLower = mb_strtolower($typeRaw, 'UTF-8');
            $resolvedTypeCode = $truckTypesByName[$typeLower] ?? ($truckTypesByCode[$typeLower] ?? null);

            if (! $resolvedTypeCode) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'Vehicle Type',
                    'value' => $typeRaw,
                    'message' => sprintf('Vehicle Type "%s" was not found in active truck types.', $typeRaw),
                ];
            } else {
                $rowData['vehicle_type'] = $resolvedTypeCode;
            }

            // 3. Resolve tyre layout
            $layoutRaw = trim((string) ($rowData['tyre_layout'] ?? ''));
            if ($layoutRaw !== '') {
                $layoutLower = mb_strtolower($layoutRaw, 'UTF-8');
                $resolvedLayoutCode = $tyreLayoutsByName[$layoutLower] ?? ($tyreLayoutsByCode[$layoutLower] ?? null);
                if (! $resolvedLayoutCode) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'Tyre Layout',
                        'value' => $layoutRaw,
                        'message' => sprintf('Tyre Layout "%s" was not found in active tyre layouts.', $layoutRaw),
                    ];
                } else {
                    $rowData['tyre_layout'] = $resolvedLayoutCode;
                }
            } else {
                $rowData['tyre_layout'] = TyreLayoutDefaults::defaultCode();
            }

            // Clean up numeric / null fields
            foreach (['make', 'model'] as $f) {
                $val = trim((string) ($rowData[$f] ?? ''));
                $rowData[$f] = $val !== '' ? $val : null;
            }

            $odoRaw = trim((string) ($rowData['odometer_km'] ?? ''));
            $rowData['odometer_km'] = ($odoRaw !== '' && is_numeric($odoRaw)) ? (float) $odoRaw : 0;

            $loadRaw = trim((string) ($rowData['max_load_ton'] ?? ''));
            $rowData['max_load_ton'] = ($loadRaw !== '' && is_numeric($loadRaw)) ? (float) $loadRaw : 0;

            $tankRaw = trim((string) ($rowData['fuel_tank_capacity_ltr'] ?? ''));
            $rowData['fuel_tank_capacity_ltr'] = ($tankRaw !== '' && is_numeric($tankRaw)) ? (float) $tankRaw : 0;

            foreach (['manufacture_year', 'default_mileage_km_per_liter'] as $f) {
                $val = trim((string) ($rowData[$f] ?? ''));
                $rowData[$f] = ($val !== '' && is_numeric($val)) ? $val : null;
            }

            return $errors;
        };

        $saveHandler = function (array $data) use ($tenant, $tenantId, $userId): Vehicle {
            $this->subscriptionGuard->assertCanCreateVehicle($tenant);

            $existing = Vehicle::withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('registration_number', $data['registration_number'])
                ->first();

            if ($existing) {
                $existing->restore();
                $existing->update([
                    ...$data,
                    'is_active' => true,
                    'updated_by' => $userId,
                ]);

                return $existing;
            }

            return Vehicle::create([
                ...$data,
                'tenant_id' => $tenantId,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        };

        $result = $bulkUploadService->process(
            file: $request->file('file'),
            modelClass: Vehicle::class,
            columnSpecs: [
                'registration_number' => [
                    'label' => 'Vehicle Number',
                    'required' => true,
                    'aliases' => ['registration_number', 'vehicle_number', 'vehicle_no', 'truck_number', 'truck_no', 'truck'],
                    'rules' => ['required', 'string', 'max:20'],
                    'unique' => true,
                ],
                'vehicle_type' => [
                    'label' => 'Vehicle Type',
                    'required' => true,
                    'aliases' => ['vehicle_type', 'truck_type', 'type'],
                    'rules' => ['required', 'string', 'max:64'],
                ],
                'make' => [
                    'label' => 'Make',
                    'required' => false,
                    'aliases' => ['make', 'brand', 'manufacturer'],
                    'rules' => ['nullable', 'string', 'max:100'],
                ],
                'model' => [
                    'label' => 'Model',
                    'required' => false,
                    'aliases' => ['model'],
                    'rules' => ['nullable', 'string', 'max:100'],
                ],
                'manufacture_year' => [
                    'label' => 'Manufacture Year',
                    'required' => false,
                    'aliases' => ['manufacture_year', 'year'],
                    'rules' => ['nullable', 'integer', 'min:1980', 'max:'.date('Y')],
                ],
                'odometer_km' => [
                    'label' => 'Odometer KM',
                    'required' => false,
                    'aliases' => ['odometer_km', 'odometer', 'km'],
                    'rules' => ['nullable', 'numeric', 'min:0'],
                ],
                'default_mileage_km_per_liter' => [
                    'label' => 'Mileage KM/L',
                    'required' => false,
                    'aliases' => ['default_mileage_km_per_liter', 'mileage', 'average'],
                    'rules' => ['nullable', 'numeric', 'min:0'],
                ],
                'max_load_ton' => [
                    'label' => 'Max Load Ton',
                    'required' => false,
                    'aliases' => ['max_load_ton', 'capacity', 'capacity_ton', 'max_capacity'],
                    'rules' => ['nullable', 'numeric', 'min:0'],
                ],
                'fuel_tank_capacity_ltr' => [
                    'label' => 'Fuel Tank Capacity Ltr',
                    'required' => false,
                    'aliases' => ['fuel_tank_capacity_ltr', 'fuel_tank_capacity', 'fuel_capacity', 'tank_capacity'],
                    'rules' => ['nullable', 'numeric', 'min:0'],
                ],
                'tyre_layout' => [
                    'label' => 'Tyre Layout',
                    'required' => false,
                    'aliases' => ['tyre_layout', 'layout'],
                    'rules' => ['nullable', 'string', 'max:64'],
                ],
            ],
            rowValidator: $rowValidator,
            saveHandler: $saveHandler,
        );

        return response()->json($result);
    }

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
