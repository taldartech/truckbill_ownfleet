<?php

namespace App\Http\Controllers\Api;

use App\Challan;
use App\Enums\ChallanPaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChallanRequest;
use App\Http\Requests\UpdateChallanRequest;
use App\Http\Resources\ChallanResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ChallanController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Challan::class);

        $perPage = min(max((int) $request->input('per_page', 25), 1), 500);
        $search = $request->filled('search') ? $request->string('search')->toString() : null;
        $status = $request->filled('status') ? $request->string('status')->toString() : null;

        $query = Challan::query()
            ->with(['vehicle:id,registration_number', 'driver:id,name'])
            ->latest('challan_date')
            ->latest('created_at');

        if ($search !== null) {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('challan_number', 'like', "%{$search}%")
                    ->orWhere('violation_type', 'like', "%{$search}%")
                    ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery
                        ->where('registration_number', 'like', "%{$search}%"))
                    ->orWhereHas('driver', fn ($driverQuery) => $driverQuery
                        ->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status !== null && in_array($status, [ChallanPaymentStatus::Paid->value, ChallanPaymentStatus::Unpaid->value], true)) {
            $query->where('status', $status);
        }

        return ChallanResource::collection($query->paginate($perPage));
    }

    public function store(StoreChallanRequest $request): ChallanResource
    {
        $validated = $request->validated();
        $validated['status'] = $validated['status'] ?? ChallanPaymentStatus::Unpaid->value;

        $challan = Challan::query()->create($validated);

        $challan->load(['vehicle:id,registration_number', 'driver:id,name']);

        return new ChallanResource($challan);
    }

    public function show(Challan $challan): ChallanResource
    {
        $this->authorize('view', $challan);
        $challan->load(['vehicle:id,registration_number', 'driver:id,name']);

        return new ChallanResource($challan);
    }

    public function update(UpdateChallanRequest $request, Challan $challan): ChallanResource
    {
        $challan->update($request->validated());
        $challan->load(['vehicle:id,registration_number', 'driver:id,name']);

        return new ChallanResource($challan);
    }

    public function destroy(Request $request, Challan $challan): Response
    {
        $this->authorize('delete', $challan);

        $validated = $request->validate([
            'deleted_reason' => ['required', 'string', 'max:1000'],
        ]);

        $challan->update([
            'deleted_by' => $request->user()?->id,
            'deleted_reason' => $validated['deleted_reason'],
        ]);

        $challan->delete();

        return response()->noContent();
    }
}
