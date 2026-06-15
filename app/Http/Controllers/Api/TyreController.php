<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InstallTyreRequest;
use App\Http\Requests\RemoveTyreInstallationRequest;
use App\Http\Requests\ScrapTyreRequest;
use App\Http\Requests\StoreTyreDamageRequest;
use App\Http\Requests\StoreTyreExpenseRequest;
use App\Http\Requests\StoreTyreReplacementRequest;
use App\Http\Requests\StoreTyreRequest;
use App\Http\Requests\StoreTyreRetreadRequest;
use App\Http\Requests\StoreTyreRotationRequest;
use App\Http\Requests\UpdateTyreRequest;
use App\Http\Requests\UpdateTyreStintRequest;
use App\Http\Resources\TyreDamageResource;
use App\Http\Resources\TyreExpenseResource;
use App\Http\Resources\TyreInstallationResource;
use App\Http\Resources\TyreResource;
use App\Http\Resources\TyreRetreadResource;
use App\Http\Resources\TyreRotationResource;
use App\Modules\TyreManagement\Services\TyreService;
use App\Tyre;
use App\TyreInstallation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TyreController extends Controller
{
    public function __construct(private readonly TyreService $tyreService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tyre::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $search = $request->filled('search') ? (string) $request->input('search') : null;
        $status = $request->filled('status') ? (string) $request->input('status') : null;
        $vehicleId = $request->filled('vehicle_id') ? (string) $request->input('vehicle_id') : null;

        return TyreResource::collection($this->tyreService->paginate($perPage, $search, $status, $vehicleId));
    }

    public function store(StoreTyreRequest $request): TyreResource
    {
        $tyre = $this->tyreService->create($request->validated());

        return new TyreResource($tyre);
    }

    public function show(Tyre $tyre): JsonResponse
    {
        $this->authorize('view', $tyre);

        $payload = $this->tyreService->showWithLifecycle($tyre->id);

        return response()->json([
            'data' => (new TyreResource($payload['tyre']))->resolve(),
            'life_summary' => $payload['life_summary'],
            'timeline' => $payload['timeline'],
        ]);
    }

    public function removalPreview(Request $request, TyreInstallation $tyreInstallation): JsonResponse
    {
        $this->authorize('view', $tyreInstallation->tyre);

        $stintKm = $request->filled('stint_km') ? (float) $request->input('stint_km') : null;
        $odometerKm = $request->filled('odometer_km') ? (float) $request->input('odometer_km') : null;

        return response()->json([
            'data' => $this->tyreService->previewRemoval($tyreInstallation, $stintKm, $odometerKm),
            'life_summary' => $this->tyreService->showWithLifecycle($tyreInstallation->tyre_id)['life_summary'],
        ]);
    }

    public function update(UpdateTyreRequest $request, Tyre $tyre): TyreResource
    {
        $tyre = $this->tyreService->update($tyre, $request->validated());

        return new TyreResource($tyre);
    }

    public function install(InstallTyreRequest $request, Tyre $tyre): TyreInstallationResource
    {
        $installation = $this->tyreService->install($tyre, $request->validated());

        return new TyreInstallationResource($installation);
    }

    public function removeInstallation(RemoveTyreInstallationRequest $request, TyreInstallation $tyreInstallation): TyreInstallationResource
    {
        $installation = $this->tyreService->removeInstallation($tyreInstallation, $request->validated());

        return new TyreInstallationResource($installation);
    }

    public function updateStintKm(UpdateTyreStintRequest $request, TyreInstallation $tyreInstallation): TyreInstallationResource
    {
        $installation = $this->tyreService->updateStintKm($tyreInstallation, $request->validated());

        return new TyreInstallationResource($installation);
    }

    public function rotate(StoreTyreRotationRequest $request, Tyre $tyre): TyreRotationResource
    {
        $rotation = $this->tyreService->rotate($tyre, $request->validated());

        return new TyreRotationResource($rotation);
    }

    public function retread(StoreTyreRetreadRequest $request, Tyre $tyre): TyreRetreadResource
    {
        $retread = $this->tyreService->recordRetread($tyre, $request->validated());

        return new TyreRetreadResource($retread);
    }

    public function damage(StoreTyreDamageRequest $request, Tyre $tyre): TyreDamageResource
    {
        $damage = $this->tyreService->recordDamage($tyre, $request->validated());

        return new TyreDamageResource($damage);
    }

    public function replace(StoreTyreReplacementRequest $request): JsonResponse
    {
        $replacement = $this->tyreService->replaceOnVehicle($request->validated());

        return response()->json([
            'data' => [
                'id' => $replacement->id,
                'vehicle_id' => $replacement->vehicle_id,
                'old_tyre_id' => $replacement->old_tyre_id,
                'new_tyre_id' => $replacement->new_tyre_id,
                'replaced_at' => $replacement->replaced_at?->toDateString(),
            ],
        ], 201);
    }

    public function scrap(ScrapTyreRequest $request, Tyre $tyre): TyreResource
    {
        $tyre = $this->tyreService->scrap($tyre, $request->validated());

        return new TyreResource($tyre);
    }

    public function expense(StoreTyreExpenseRequest $request, Tyre $tyre): TyreExpenseResource
    {
        $expense = $this->tyreService->recordMaintenanceExpense($tyre, $request->validated());

        return new TyreExpenseResource($expense);
    }

    public function vehiclePositions(Request $request, string $vehicle): JsonResponse
    {
        $this->authorize('viewAny', Tyre::class);

        return response()->json([
            'data' => $this->tyreService->vehiclePositions($vehicle),
        ]);
    }

    public function vehicleTyreHistory(Request $request, string $vehicle): JsonResponse
    {
        $this->authorize('viewAny', Tyre::class);

        return response()->json($this->tyreService->vehicleTyreHistory($vehicle));
    }
}
