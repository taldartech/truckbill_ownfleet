<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Http\Resources\VehicleResource;
use App\Vehicle;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class VehicleController
{
    public function index(): AnonymousResourceCollection
    {
        return VehicleResource::collection(Vehicle::query()->latest()->paginate());
    }

    public function store(StoreVehicleRequest $request): VehicleResource
    {
        $vehicle = Vehicle::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return new VehicleResource($vehicle);
    }

    public function show(Vehicle $vehicle): VehicleResource
    {
        return new VehicleResource($vehicle);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): VehicleResource
    {
        $vehicle->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return new VehicleResource($vehicle->fresh());
    }

    public function destroy(Vehicle $vehicle): Response
    {
        $vehicle->delete();

        return response()->noContent();
    }
}
