<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTruckTypeRequest;
use App\Http\Requests\UpdateTruckTypeRequest;
use App\Http\Resources\TruckTypeResource;
use App\Support\CurrentTenant;
use App\Support\TruckTypeDefaults;
use App\TruckType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TruckTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TruckType::class);

        $tenantId = CurrentTenant::requireId();
        TruckTypeDefaults::seedForTenant($tenantId);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;

        $query = TruckType::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->where('is_active', true)
            ->orderBy('name');

        if ($search !== null && $search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($perPage >= 500) {
            return TruckTypeResource::collection($query->get());
        }

        return TruckTypeResource::collection($query->paginate($perPage));
    }

    public function store(StoreTruckTypeRequest $request): TruckTypeResource
    {
        $truckType = TruckType::query()->create([
            ...$request->validated(),
            'tenant_id' => CurrentTenant::requireId(),
            'is_active' => true,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return new TruckTypeResource($truckType->load(['createdBy:id,name', 'updatedBy:id,name']));
    }

    public function show(TruckType $truckType): TruckTypeResource
    {
        $this->authorize('view', $truckType);

        return new TruckTypeResource($truckType->load(['createdBy:id,name', 'updatedBy:id,name']));
    }

    public function update(UpdateTruckTypeRequest $request, TruckType $truckType): TruckTypeResource
    {
        $truckType->update([
            'name' => $request->validated('name'),
            'updated_by' => $request->user()?->id,
        ]);

        return new TruckTypeResource($truckType->fresh(['createdBy:id,name', 'updatedBy:id,name']));
    }

    public function destroy(): JsonResponse
    {
        return response()->json([
            'message' => 'Truck types cannot be deleted.',
        ], 403);
    }
}
