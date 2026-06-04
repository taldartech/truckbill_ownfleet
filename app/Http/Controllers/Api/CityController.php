<?php

namespace App\Http\Controllers\Api;

use App\City;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCityRequest;
use App\Http\Requests\UpdateCityRequest;
use App\Http\Resources\CityResource;
use App\Modules\LocationManagement\Services\CityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CityController extends Controller
{
    public function __construct(private readonly CityService $cityService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', City::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $search = $request->filled('search') ? (string) $request->input('search') : null;
        $stateId = $request->filled('state_id') ? (int) $request->input('state_id') : null;

        return CityResource::collection($this->cityService->paginate($perPage, $stateId, $search));
    }

    public function store(StoreCityRequest $request): CityResource
    {
        $city = $this->cityService->create($request->validated());

        return new CityResource($city);
    }

    public function show(City $city): CityResource
    {
        $this->authorize('view', $city);

        return new CityResource($city->load('state:id,name'));
    }

    public function update(UpdateCityRequest $request, City $city): CityResource
    {
        $city = $this->cityService->update($city, $request->validated());

        return new CityResource($city);
    }
}
