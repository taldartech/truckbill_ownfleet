<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTyreLayoutRequest;
use App\Http\Requests\UpdateTyreLayoutRequest;
use App\Http\Resources\TyreLayoutResource;
use App\Support\CurrentTenant;
use App\Support\TyreLayoutDefaults;
use App\TyreLayout;
use App\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class TyreLayoutController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TyreLayout::class);

        $query = TyreLayout::query()
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);

        if ($perPage >= 500) {
            return TyreLayoutResource::collection($query->get());
        }

        return TyreLayoutResource::collection($query->paginate($perPage));
    }

    public function store(StoreTyreLayoutRequest $request): TyreLayoutResource
    {
        $tenantId = CurrentTenant::id();
        $data = $this->normalizePayload($request->validated(), $tenantId);

        $layout = TyreLayout::query()->create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return new TyreLayoutResource($layout);
    }

    public function show(TyreLayout $tyreLayout): TyreLayoutResource
    {
        $this->authorize('view', $tyreLayout);

        return new TyreLayoutResource($tyreLayout);
    }

    public function update(UpdateTyreLayoutRequest $request, TyreLayout $tyreLayout): TyreLayoutResource
    {
        $inUse = Vehicle::query()
            ->where('tenant_id', $tyreLayout->tenant_id)
            ->where('tyre_layout', $tyreLayout->code)
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'tyre_layout' => 'This layout is assigned to one or more trucks and cannot be modified.',
            ]);
        }

        $data = $this->normalizePayload($request->validated(), $tyreLayout->tenant_id, $tyreLayout);

        $tyreLayout->update($data);

        return new TyreLayoutResource($tyreLayout->fresh());
    }

    public function destroy(Request $request, TyreLayout $tyreLayout): Response
    {
        $this->authorize('delete', $tyreLayout);
    
        $validated = $request->validate([
            'deleted_reason' => ['required', 'string', 'max:1000'],
        ]);
    
        $inUse = Vehicle::query()
            ->where('tenant_id', $tyreLayout->tenant_id)
            ->where('tyre_layout', $tyreLayout->code)
            ->exists();
    
        if ($inUse) {
            throw ValidationException::withMessages([
                'tyre_layout' => 'This layout is assigned to one or more trucks and cannot be deleted.',
            ]);
        }
    
        $tyreLayout->update([
            'deleted_by' => $request->user()?->id,
            'deleted_reason' => $validated['deleted_reason'],
        ]);
    
        $tyreLayout->delete();
    
        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data, string $tenantId, ?TyreLayout $existing = null): array
    {
        $layoutType = $data['layout_type'] ?? $existing?->layout_type ?? TyreLayout::TYPE_STANDARD;

        if ($layoutType === TyreLayout::TYPE_LEGACY_DUAL_REAR) {
            $data['steer_axles'] = 0;
            $data['dual_axles'] = [];
            $data['single_axles'] = [];
            $data['spare_count'] = 0;
        } else {
            $data['steer_axles'] = (int) ($data['steer_axles'] ?? $existing?->steer_axles ?? 1);
            $data['dual_axles'] = array_values(array_unique(array_map('intval', $data['dual_axles'] ?? $existing?->dual_axles ?? [])));
            sort($data['dual_axles']);
            $data['single_axles'] = array_values(array_unique(array_map('intval', $data['single_axles'] ?? $existing?->single_axles ?? [])));
            sort($data['single_axles']);
            $data['spare_count'] = (int) ($data['spare_count'] ?? $existing?->spare_count ?? 0);
        }

        $name = (string) ($data['name'] ?? $existing?->name ?? '');
        $data['code'] = isset($data['code']) && $data['code'] !== ''
            ? strtoupper((string) $data['code'])
            : TyreLayoutDefaults::generateCode($name, $tenantId, $existing?->id);

        $data['sort_order'] = (int) ($data['sort_order'] ?? $existing?->sort_order ?? 0);
        $data['is_active'] = array_key_exists('is_active', $data)
            ? (bool) $data['is_active']
            : ($existing?->is_active ?? true);

        return $data;
    }
}
