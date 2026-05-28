<?php

namespace App\Http\Controllers\Api;

use App\Driver;
use App\Http\Requests\StoreDriverRequest;
use App\Http\Requests\UpdateDriverRequest;
use App\Http\Resources\DriverResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DriverController
{
    public function index(): AnonymousResourceCollection
    {
        return DriverResource::collection(Driver::query()->latest()->paginate());
    }

    public function store(StoreDriverRequest $request): DriverResource
    {
        $driver = Driver::query()->create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return new DriverResource($driver);
    }

    public function show(Driver $driver): DriverResource
    {
        return new DriverResource($driver);
    }

    public function update(UpdateDriverRequest $request, Driver $driver): DriverResource
    {
        $driver->update([
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return new DriverResource($driver->fresh());
    }

    public function destroy(Driver $driver): Response
    {
        $driver->delete();

        return response()->noContent();
    }
}
