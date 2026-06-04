<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStateRequest;
use App\Http\Requests\UpdateStateRequest;
use App\Http\Resources\StateResource;
use App\Modules\LocationManagement\Services\StateService;
use App\State;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StateController extends Controller
{
    public function __construct(private readonly StateService $stateService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', State::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $search = $request->filled('search') ? (string) $request->input('search') : null;

        return StateResource::collection($this->stateService->paginate($perPage, $search));
    }

    public function store(StoreStateRequest $request): StateResource
    {
        $state = $this->stateService->create($request->validated());

        return new StateResource($state->loadCount('cities'));
    }

    public function show(State $state): StateResource
    {
        $this->authorize('view', $state);

        return new StateResource($state->loadCount('cities'));
    }

    public function update(UpdateStateRequest $request, State $state): StateResource
    {
        $state = $this->stateService->update($state, $request->validated());

        return new StateResource($state);
    }
}
