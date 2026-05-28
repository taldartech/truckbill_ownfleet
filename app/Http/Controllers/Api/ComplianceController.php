<?php

namespace App\Http\Controllers\Api;

use App\Challan;
use App\Emi;
use App\Http\Requests\StoreChallanRequest;
use App\Http\Requests\StoreEmiRequest;
use Illuminate\Http\JsonResponse;

class ComplianceController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'challans' => Challan::query()->latest()->paginate(),
            'emis' => Emi::query()->latest()->paginate(),
        ]);
    }

    public function storeChallan(StoreChallanRequest $request): JsonResponse
    {
        $challan = Challan::query()->create($request->validated());

        return response()->json($challan, 201);
    }

    public function storeEmi(StoreEmiRequest $request): JsonResponse
    {
        $emi = Emi::query()->create($request->validated());

        return response()->json($emi, 201);
    }
}
