<?php

namespace App\Http\Controllers\Api;

use App\Driver;
use App\DriverAdvanceEntry;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDriverAdvanceEntryRequest;
use App\Http\Resources\DriverAdvanceEntryResource;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;

class DriverAdvanceController extends Controller
{
    public function ledger(Driver $driver): JsonResponse
    {
        $this->authorize('view', $driver);

        $entries = DriverAdvanceEntry::query()
            ->where('driver_id', $driver->id)
            ->orderByDesc('entry_date')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => [
                'driver_id' => $driver->id,
                'driver_name' => $driver->name,
                'balance' => DriverAdvanceEntry::balanceFor($driver),
                'entries' => DriverAdvanceEntryResource::collection($entries),
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
