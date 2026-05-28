<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreVehicleDocumentRequest;
use App\Http\Resources\VehicleDocumentResource;
use App\VehicleDocument;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class VehicleDocumentController
{
    public function index(): AnonymousResourceCollection
    {
        return VehicleDocumentResource::collection(VehicleDocument::query()->latest()->paginate());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVehicleDocumentRequest $request): VehicleDocumentResource
    {
        $vehicleDocument = VehicleDocument::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return new VehicleDocumentResource($vehicleDocument);
    }

    /**
     * Display the specified resource.
     */
    public function show(VehicleDocument $vehicleDocument): VehicleDocumentResource
    {
        return new VehicleDocumentResource($vehicleDocument);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreVehicleDocumentRequest $request, VehicleDocument $vehicleDocument): VehicleDocumentResource
    {
        $vehicleDocument->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return new VehicleDocumentResource($vehicleDocument->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(VehicleDocument $vehicleDocument): Response
    {
        $vehicleDocument->delete();

        return response()->noContent();
    }
}
