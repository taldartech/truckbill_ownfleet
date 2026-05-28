<?php

namespace App\Http\Controllers\Api;

use App\Driver;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDriverRequest;
use App\Http\Requests\UpdateDriverRequest;
use App\Http\Resources\DriverResource;
use App\Modules\DriverManagement\Services\DriverService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    public function __construct(private readonly DriverService $driverService)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Driver::class);

        return DriverResource::collection($this->driverService->paginate());
    }

    public function store(StoreDriverRequest $request): DriverResource
    {
        $driver = $this->driverService->create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return new DriverResource($driver);
    }

    public function show(Driver $driver): DriverResource
    {
        $this->authorize('view', $driver);

        return new DriverResource($driver);
    }

    public function update(UpdateDriverRequest $request, Driver $driver): DriverResource
    {
        $driver = $this->driverService->update($driver, [
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        return new DriverResource($driver);
    }

    public function destroy(Driver $driver): Response
    {
        $this->authorize('delete', $driver);
        $this->driverService->delete($driver);

        return response()->noContent();
    }

    public function licenseExpiring(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Driver::class);

        $days = (int) $request->integer('days', 30);
        $days = max(1, min($days, 365));

        return DriverResource::collection($this->driverService->licenseExpiringWithinDays($days));
    }
}
