<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStationRequest;
use App\Http\Requests\UpdateStationRequest;
use App\Http\Resources\StationResource;
use App\Modules\TripManagement\Services\StationService;
use App\Station;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StationController extends Controller
{
    public function __construct(private readonly StationService $stationService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Station::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);
        $search = $request->filled('search') ? (string) $request->input('search') : null;

        if ($perPage >= 500) {
            return StationResource::collection($this->stationService->listAll($search));
        }

        return StationResource::collection($this->stationService->paginate($perPage, $search));
    }

    public function store(StoreStationRequest $request): StationResource
    {
        $station = $this->stationService->create($request->validated());

        return new StationResource($station);
    }

    public function show(Station $station): StationResource
    {
        $this->authorize('view', $station);

        return new StationResource($station);
    }

    public function update(UpdateStationRequest $request, Station $station): StationResource
    {
        $station = $this->stationService->update($station, $request->validated());

        return new StationResource($station);
    }

    public function destroy(Station $station): Response
    {
        $this->authorize('delete', $station);
        $this->stationService->delete($station);

        return response()->noContent();
    }
}
