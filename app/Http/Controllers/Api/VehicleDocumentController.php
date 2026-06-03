<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleDocumentRequest;
use App\Http\Resources\VehicleDocumentResource;
use App\Support\CurrentTenant;
use App\Support\FleetDocumentStorage;
use App\VehicleDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleDocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VehicleDocument::class);

        $perPage = min(max((int) $request->input('per_page', 50), 1), 500);

        $query = VehicleDocument::query()->with('vehicle')->latest();

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->string('vehicle_id'));
        }

        if ($request->boolean('expiring_soon')) {
            $days = max(1, min((int) $request->input('days', 30), 365));
            $query->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', now()->addDays($days)->toDateString())
                ->whereDate('expiry_date', '>=', now()->toDateString());
        }

        return VehicleDocumentResource::collection($query->paginate($perPage));
    }

    public function store(StoreVehicleDocumentRequest $request): VehicleDocumentResource
    {
        $data = $this->mergeUploadedFile($request);

        $document = VehicleDocument::query()->create([
            ...$data,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return new VehicleDocumentResource($document->load('vehicle'));
    }

    public function show(VehicleDocument $vehicleDocument): VehicleDocumentResource
    {
        $this->authorize('view', $vehicleDocument);

        return new VehicleDocumentResource($vehicleDocument->load('vehicle'));
    }

    public function update(StoreVehicleDocumentRequest $request, VehicleDocument $vehicleDocument): VehicleDocumentResource
    {
        $data = $this->mergeUploadedFile($request, $vehicleDocument);

        $vehicleDocument->update([
            ...$data,
            'updated_by' => $request->user()?->id,
        ]);

        return new VehicleDocumentResource($vehicleDocument->fresh('vehicle'));
    }

    public function download(VehicleDocument $vehicleDocument): StreamedResponse
    {
        $this->authorize('view', $vehicleDocument);

        return FleetDocumentStorage::downloadResponse($vehicleDocument);
    }

    public function preview(VehicleDocument $vehicleDocument): StreamedResponse
    {
        $this->authorize('view', $vehicleDocument);

        return FleetDocumentStorage::previewResponse($vehicleDocument);
    }

    public function destroy(VehicleDocument $vehicleDocument): Response
    {
        $this->authorize('delete', $vehicleDocument);
        $vehicleDocument->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeUploadedFile(
        StoreVehicleDocumentRequest $request,
        ?VehicleDocument $existing = null,
    ): array {
        $data = $request->safe()->except(['file']);
        $tenantId = CurrentTenant::requireId();

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $vehicleId = (string) ($data['vehicle_id'] ?? $existing?->vehicle_id);
            $documentType = (string) ($data['document_type'] ?? $existing?->document_type?->value ?? $existing?->document_type);

            $fileMeta = $existing !== null
                ? FleetDocumentStorage::replace($existing, $file)
                : FleetDocumentStorage::store($tenantId, $vehicleId, $documentType, $file);

            $data = [...$data, ...$fileMeta];
        }

        return $data;
    }
}
