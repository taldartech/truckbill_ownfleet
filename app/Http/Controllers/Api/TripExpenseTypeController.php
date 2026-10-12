<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTripExpenseTypeRequest;
use App\Http\Requests\UpdateTripExpenseTypeRequest;
use App\Http\Resources\TripExpenseTypeResource;
use App\TripExpenseTypeMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TripExpenseTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', TripExpenseTypeMaster::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;
        $activeOnly = $request->boolean('active_only') || $request->boolean('is_active');

        $query = TripExpenseTypeMaster::query()
            ->orderBy('name');

        if (! $request->boolean('include_system') && ! $request->has('all')) {
            $query->whereNotIn('code', TripExpenseTypeMaster::SYSTEM_CODES);
        }

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($search !== null && $search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($perPage >= 500 || $request->has('all')) {
            return TripExpenseTypeResource::collection($query->get());
        }

        return TripExpenseTypeResource::collection($query->paginate($perPage));
    }

    public function store(StoreTripExpenseTypeRequest $request): TripExpenseTypeResource
    {
        TripExpenseTypeMaster::onlyTrashed()->where(fn ($q) => $q->where('code', $request->code)->orWhere('name', $request->name))->forceDelete();

        $tripExpenseType = TripExpenseTypeMaster::query()->create([
            ...$request->validated(),
            'tenant_id' => null,
            'is_active' => true,
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $tripExpenseType->load(['createdBy', 'updatedBy']);

        return new TripExpenseTypeResource($tripExpenseType);
    }

    public function show(TripExpenseTypeMaster $tripExpenseType): TripExpenseTypeResource
    {
        $this->authorize('view', $tripExpenseType);

        $tripExpenseType->load(['createdBy', 'updatedBy']);

        return new TripExpenseTypeResource($tripExpenseType);
    }

    public function update(UpdateTripExpenseTypeRequest $request, TripExpenseTypeMaster $tripExpenseType): TripExpenseTypeResource
    {
        if ($tripExpenseType->isSystem()) {
            abort(422, 'Default system expense types cannot be modified.');
        }

        $data = [
            'updated_by' => $request->user()?->id,
        ];

        if ($request->has('name')) {
            $data['name'] = $request->validated('name');
        }

        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active');
        }

        $tripExpenseType->update($data);
        $tripExpenseType->load(['createdBy', 'updatedBy']);

        return new TripExpenseTypeResource($tripExpenseType);
    }

    public function destroy(TripExpenseTypeMaster $tripExpenseType): JsonResponse
    {
        $this->authorize('delete', $tripExpenseType);

        if ($tripExpenseType->isSystem()) {
            abort(422, 'Default system expense types cannot be deleted.');
        }

        $tripExpenseType->delete();

        return response()->json([
            'message' => 'Trip expense type deleted successfully.',
        ]);
    }
}
