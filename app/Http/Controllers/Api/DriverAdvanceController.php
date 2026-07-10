<?php

namespace App\Http\Controllers\Api;

use App\Driver;
use App\DriverAdvanceEntry;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDriverAdvanceEntryRequest;
use App\Http\Resources\DriverAdvanceEntryResource;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverAdvanceController extends Controller
{
    public function ledger(Request $request, Driver $driver): JsonResponse
    {
        $this->authorize('viewAdvances', $driver);

        $perPage = min(max((int) $request->input('per_page', 5), 1), 50);

        $entries = DriverAdvanceEntry::query()
            ->where('driver_id', $driver->id)
            ->orderByDesc('entry_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => [
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'balance' => DriverAdvanceEntry::balanceFor($driver),
                'entries' => DriverAdvanceEntryResource::collection($entries),
                'meta' => [
                    'current_page' => $entries->currentPage(),
                    'per_page' => $entries->perPage(),
                    'last_page' => $entries->lastPage(),
                    'total' => $entries->total(),
                    'from' => $entries->firstItem() ?? 0,
                    'to' => $entries->lastItem() ?? 0,
                ],
            ],
        ]);
    }

    public function store(StoreDriverAdvanceEntryRequest $request, Driver $driver): JsonResponse
    {
        $entry = DriverAdvanceEntry::query()->create([
            'tenant_id' => CurrentTenant::requireId(),
            'driver_id' => $driver->id,
            'entry_type' => $request->validated('entry_type'),
            'amount' => $request->validated('amount'),
            'entry_date' => $request->validated('entry_date'),
            'remarks' => $request->validated('remarks'),
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'data' => [
                'entry' => new DriverAdvanceEntryResource($entry),
                'balance' => DriverAdvanceEntry::balanceFor($driver),
            ],
        ], 201);
    }
}
