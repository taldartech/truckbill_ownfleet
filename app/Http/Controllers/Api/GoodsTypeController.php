<?php

namespace App\Http\Controllers\Api;

use App\Exports\GoodsTypesTemplateExport;
use App\GoodsType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoodsTypeRequest;
use App\Http\Requests\UpdateGoodsTypeRequest;
use App\Http\Resources\GoodsTypeResource;
use App\Imports\GoodsTypesBulkImport;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Maatwebsite\Excel\Facades\Excel;

class GoodsTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', GoodsType::class);

        CurrentTenant::requireId();

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;

        $query = GoodsType::query()
            ->orderBy('name');

        if ($search !== null && $search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%");
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

    public function destroy(
        Request $request,
        GoodsType $goodsType,
    ): JsonResponse {
        $this->authorize('delete', $goodsType);

        $validated = $request->validate([
            'deleted_reason' => ['required', 'string', 'max:1000'],
        ]);

        $goodsType->update([
            'deleted_by' => $request->user()?->id,
            'deleted_reason' => $validated['deleted_reason'],
        ]);

        $goodsType->delete();

        return response()->json([
            'message' => 'Goods type deleted successfully.',
        ]);
    }

    public function downloadBulkTemplate()
{
    $this->authorize('create', GoodsType::class);

    CurrentTenant::requireId();

    return Excel::download(
        new GoodsTypesTemplateExport(),
        'goods_types_template.xlsx'
    );
}

public function bulkUpload(Request $request): JsonResponse
{
    $this->authorize('create', GoodsType::class);

    $tenantId = CurrentTenant::requireId();

    $request->validate([
        'file' => [
            'required',
            'file',
            'mimes:xlsx,xls,csv',
            'max:10240',
        ],
    ]);

    $import = new GoodsTypesBulkImport(
        $tenantId,
        $request->user()?->id,
    );

    Excel::import($import, $request->file('file'));

    return response()->json([
        'success' => true,
        'message' => 'Bulk upload completed.',
        'total' => $import->total,
        'successful' => $import->successful,
        'failed' => $import->failed,
        'errors' => $import->errors,
    ]);
}
}
