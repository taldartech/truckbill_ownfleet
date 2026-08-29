<?php

namespace App\Http\Controllers\Api;

use App\Broker;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrokerRequest;
use App\Http\Requests\UpdateBrokerRequest;
use App\Http\Resources\BrokerResource;
use App\Modules\TripManagement\Services\BrokerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BrokerController extends Controller
{
    public function __construct(private readonly BrokerService $brokerService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Broker::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);
        $search = $request->filled('search') ? (string) $request->input('search') : null;

        if ($perPage >= 500) {
            return BrokerResource::collection($this->brokerService->listAll($search));
        }

        return BrokerResource::collection($this->brokerService->paginate($perPage, $search));
    }

    public function store(StoreBrokerRequest $request): BrokerResource
    {
        $broker = $this->brokerService->create($request->validated(), $request->user()?->id);

        return new BrokerResource($broker);
    }

    public function show(Broker $broker): BrokerResource
    {
        $this->authorize('view', $broker);

        return new BrokerResource($broker);
    }

    public function update(UpdateBrokerRequest $request, Broker $broker): BrokerResource
    {
        $broker = $this->brokerService->update($broker, $request->validated(), $request->user()?->id);

        return new BrokerResource($broker);
    }

    public function destroy(Request $request, Broker $broker): Response
    {
        $this->authorize('delete', $broker);
    
        $request->validate([
            'deleted_reason' => ['required', 'string', 'max:1000'],
        ]);
    
        $broker->update([
            'deleted_by' => $request->user()?->id,
            'deleted_reason' => $request->input('deleted_reason'),
        ]);
    
        $this->brokerService->delete($broker);
    
        return response()->noContent();
    }
}
