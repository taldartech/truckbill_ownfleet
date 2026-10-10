<?php

namespace App\Http\Controllers\Api;

use App\GoodsType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoodsTypeRequest;
use App\Http\Requests\UpdateGoodsTypeRequest;
use App\Http\Resources\GoodsTypeResource;
use App\Services\BulkUploadService;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoodsTypeController extends Controller
{
    public function bulkUploadTemplate(BulkUploadService $bulkUploadService): StreamedResponse
    {
        $this->authorize('viewAny', GoodsType::class);

        return $bulkUploadService->downloadTemplate(
            headers: [
                'Goods Type Name',
                'Code',
            ],
            fileName: 'goods_types_bulk_upload_template.csv'
        );
    }

    public function bulkUpload(Request $request, BulkUploadService $bulkUploadService): JsonResponse
    {
        $this->authorize('create', GoodsType::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'extensions:csv,xlsx,xls', 'max:5120'],
        ], [
            'file.mimes' => 'The uploaded file must be a CSV or Excel file (.csv, .xlsx, .xls).',
            'file.extensions' => 'The uploaded file must be a CSV or Excel file (.csv, .xlsx, .xls).',
        ]);

        $tenantId = CurrentTenant::requireId();
        $userId = $request->user()?->id;

        $saveHandler = function (array $data) use ($tenantId, $userId): GoodsType {
            return GoodsType::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'code' => ! empty($data['code']) ? $data['code'] : null,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        };

        $result = $bulkUploadService->process(
            file: $request->file('file'),
            modelClass: GoodsType::class,
            columnSpecs: [
                'name' => [
                    'label' => 'Goods Type Name',
                    'required' => true,
                    'aliases' => ['goods_type_name', 'name', 'goods_type', 'goods', 'item_name'],
                    'rules' => ['required', 'string', 'max:255'],
                    'unique' => true,
                ],
                'code' => [
                    'label' => 'Code',
                    'required' => false,
                    'aliases' => ['code', 'goods_code', 'type_code'],
                    'rules' => ['nullable', 'string', 'max:50'],
                ],
            ],
            saveHandler: $saveHandler,
        );

        return response()->json($result);
    }

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
