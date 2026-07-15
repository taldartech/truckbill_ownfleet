<?php

namespace App\Http\Controllers\Api;

use App\GoodsType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoodsTypeRequest;
use App\Http\Requests\UpdateGoodsTypeRequest;
use App\Http\Resources\GoodsTypeResource;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GoodsTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', GoodsType::class);

        CurrentTenant::requireId();

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;

        $query = GoodsType::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($search !== null && $search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($perPage >= 500) {
            return GoodsTypeResource::collection($query->get());
        }

        return GoodsTypeResource::collection($query->paginate($perPage));
    }

    public function store(StoreGoodsTypeRequest $request): GoodsTypeResource
    {
        $goodsType = GoodsType::query()->create([
            ...$request->validated(),
            'tenant_id' => CurrentTenant::requireId(),
            'is_active' => true,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $goodsType->load(['createdBy', 'updatedBy']);

        return new GoodsTypeResource($goodsType);
    }

    public function show(GoodsType $goodsType): GoodsTypeResource
    {
        $this->authorize('view', $goodsType);

        $goodsType->load(['createdBy', 'updatedBy']);

        return new GoodsTypeResource($goodsType);
    }

    public function update(UpdateGoodsTypeRequest $request, GoodsType $goodsType): GoodsTypeResource
    {
        $goodsType->update([
            'name' => $request->validated('name'),
            'updated_by' => $request->user()?->id,
        ]);

        $goodsType->load(['createdBy', 'updatedBy']);

        return new GoodsTypeResource($goodsType);
    }

    public function destroy(GoodsType $goodsType): JsonResponse
    {
        $this->authorize('delete', $goodsType);

        $goodsType->update([
            'is_active' => false,
            'updated_by' => request()->user()?->id,
        ]);

        return response()->json([
            'message' => 'Goods type removed.',
        ]);
    }
}
