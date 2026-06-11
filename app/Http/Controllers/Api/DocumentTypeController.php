<?php

namespace App\Http\Controllers\Api;

use App\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentTypeRequest;
use App\Http\Requests\UpdateDocumentTypeRequest;
use App\Http\Resources\DocumentTypeResource;
use App\Support\CurrentTenant;
use App\Support\DocumentTypeDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', DocumentType::class);

        $tenantId = CurrentTenant::requireId();
        DocumentTypeDefaults::seedForTenant($tenantId);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;

        $query = DocumentType::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($search !== null && $search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($perPage >= 500) {
            return DocumentTypeResource::collection($query->get());
        }

        return DocumentTypeResource::collection($query->paginate($perPage));
    }

    public function store(StoreDocumentTypeRequest $request): DocumentTypeResource
    {
        $documentType = DocumentType::query()->create([
            ...$request->validated(),
            'tenant_id' => CurrentTenant::requireId(),
            'is_active' => true,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        return new DocumentTypeResource($documentType);
    }

    public function show(DocumentType $documentType): DocumentTypeResource
    {
        $this->authorize('view', $documentType);

        return new DocumentTypeResource($documentType);
    }

    public function update(UpdateDocumentTypeRequest $request, DocumentType $documentType): DocumentTypeResource
    {
        $documentType->update([
            'name' => $request->validated('name'),
            'updated_by' => $request->user()?->id,
        ]);

        return new DocumentTypeResource($documentType->fresh());
    }

    public function destroy(): JsonResponse
    {
        return response()->json([
            'message' => 'Document types cannot be deleted.',
        ], 403);
    }
}
