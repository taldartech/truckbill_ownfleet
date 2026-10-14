<?php

namespace App\Http\Controllers\Api;

use App\Battery;
use App\BatteryInstallation;
use App\Http\Controllers\Controller;
use App\Http\Requests\InstallBatteryRequest;
use App\Http\Requests\RemoveBatteryInstallationRequest;
use App\Http\Requests\ReplaceBatteryRequest;
use App\Http\Requests\ScrapBatteryRequest;
use App\Http\Requests\StoreBatteryInspectionRequest;
use App\Http\Requests\StoreBatteryRequest;
use App\Http\Requests\UpdateBatteryRequest;
use App\Http\Resources\BatteryInspectionResource;
use App\Http\Resources\BatteryInstallationResource;
use App\Http\Resources\BatteryReplacementResource;
use App\Http\Resources\BatteryResource;
use App\Modules\BatteryManagement\Services\BatteryService;
use App\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BatteryController extends Controller
{
    public function __construct(
        private readonly BatteryService $batteryService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Battery::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $search = $request->filled('search') ? (string) $request->input('search') : null;
        $status = $request->filled('status') ? (string) $request->input('status') : null;
        $brand = $request->filled('brand') ? (string) $request->input('brand') : null;
        $vehicleId = $request->filled('vehicle_id') ? (string) $request->input('vehicle_id') : null;
        $warranty = in_array($request->input('warranty'), ['active', 'expiring', 'expired'], true)
            ? (string) $request->input('warranty')
            : null;

        $batteries = $this->batteryService->paginate($perPage, $search, $status, $brand, $vehicleId, $warranty);

        return BatteryResource::collection($batteries);
    }

    public function counts(): JsonResponse
    {
        $this->authorize('viewAny', Battery::class);

        return response()->json([
            'counts' => $this->batteryService->getCounts(),
            'brands' => $this->batteryService->getBrands(),
        ]);
    }

    public function inStock(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Battery::class);

        $brand = $request->filled('brand') ? (string) $request->input('brand') : null;
        $batteries = $this->batteryService->getInStockList($brand);

        return response()->json([
            'data' => $batteries->map(fn (Battery $battery): array => [
                'id' => $battery->id,
                'serial_number' => $battery->serial_number,
                'brand' => $battery->brand,
                'model' => $battery->model,
                'capacity_ah' => (float) $battery->capacity_ah,
                'voltage' => $battery->voltage,
                'status' => $battery->status?->value,
                'status_label' => $battery->status?->label(),
                'purchase_date' => $battery->purchase_date?->toDateString(),
            ])->values(),
        ]);
    }

    public function store(StoreBatteryRequest $request): JsonResponse
    {
        $battery = $this->batteryService->create($request->validated());

        return (new BatteryResource($battery))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Battery $battery): JsonResponse
    {
        $this->authorize('view', $battery);

        $payload = $this->batteryService->showWithLifecycle($battery->id);

        return response()->json([
            'data' => (new BatteryResource($payload['battery']))->resolve(),
            'life_summary' => $payload['life_summary'],
            'replacements' => BatteryReplacementResource::collection($payload['replacements'])->resolve(),
        ]);
    }

    public function update(UpdateBatteryRequest $request, Battery $battery): BatteryResource
    {
        $updated = $this->batteryService->update($battery, $request->validated());

        return new BatteryResource($updated);
    }

    public function scrap(ScrapBatteryRequest $request, Battery $battery): BatteryResource
    {
        $scrapped = $this->batteryService->scrap($battery, $request->validated());

        return new BatteryResource($scrapped);
    }

    public function destroy(Battery $battery): Response
    {
        $this->authorize('delete', $battery);
        $this->batteryService->delete($battery);

        return response()->noContent();
    }

    public function install(InstallBatteryRequest $request, Battery $battery): BatteryInstallationResource
    {
        $installation = $this->batteryService->install($battery, $request->validated());

        return new BatteryInstallationResource($installation);
    }

    public function replace(ReplaceBatteryRequest $request): JsonResponse
    {
        $replacement = $this->batteryService->replaceOnVehicle($request->validated());

        return (new BatteryReplacementResource($replacement))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function removeInstallation(RemoveBatteryInstallationRequest $request, BatteryInstallation $batteryInstallation): BatteryInstallationResource
    {
        $installation = $this->batteryService->removeInstallation($batteryInstallation, $request->validated());

        return new BatteryInstallationResource($installation);
    }

    public function storeInspection(StoreBatteryInspectionRequest $request, Battery $battery): JsonResponse
    {
        $inspection = $this->batteryService->addInspection($battery, $request->validated());

        return (new BatteryInspectionResource($inspection))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function vehicleBatteries(Request $request, Vehicle $vehicle): JsonResponse
    {
        $this->authorize('viewAny', Battery::class);

        $data = $this->batteryService->vehicleBatteries($vehicle);

        return response()->json([
            'vehicle' => [
                'id' => $vehicle->id,
                'registration_number' => $vehicle->registration_number,
                'odometer_km' => (float) $vehicle->odometer_km,
            ],
            'active_batteries' => BatteryInstallationResource::collection($data['active_batteries']),
            'history' => BatteryInstallationResource::collection($data['history']),
            'replacements' => BatteryReplacementResource::collection($data['replacements']),
        ]);
    }
}
