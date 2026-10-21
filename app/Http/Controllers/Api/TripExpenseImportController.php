<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyzeTripExpenseImportRequest;
use App\Http\Requests\PreviewTripExpenseImportRequest;
use App\Http\Requests\StoreTripExpenseImportRequest;
use App\Services\TripExpenseImport\FastagStatementImporter;
use App\Services\TripExpenseImport\FuelStatementImporter;
use App\Services\TripExpenseImport\StatementColumnMapper;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class TripExpenseImportController extends Controller
{
    public function analyze(
        AnalyzeTripExpenseImportRequest $request,
        FastagStatementImporter $fastagImporter,
        FuelStatementImporter $fuelImporter,
    ): JsonResponse {
        $importer = $this->resolveImporter($request->string('import_type')->toString(), $fastagImporter, $fuelImporter);

        try {
            $payload = $importer->analyze(
                $request->file('file'),
                $request->filled('header_row') ? $request->integer('header_row') : null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($payload);
    }

    public function preview(
        PreviewTripExpenseImportRequest $request,
        FastagStatementImporter $fastagImporter,
        FuelStatementImporter $fuelImporter,
    ): JsonResponse {
        $importer = $this->resolveImporter($request->string('import_type')->toString(), $fastagImporter, $fuelImporter);

        try {
            $payload = $importer->preview(
                $request->file('file'),
                $request->string('statement_date')->toString(),
                $request->input('column_map', []),
                $request->filled('header_row') ? $request->integer('header_row') : null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($payload);
    }

    public function store(
        StoreTripExpenseImportRequest $request,
        FastagStatementImporter $fastagImporter,
        FuelStatementImporter $fuelImporter,
    ): JsonResponse {
        $importer = $this->resolveImporter($request->string('import_type')->toString(), $fastagImporter, $fuelImporter);
        $userId = (string) $request->user()->id;

        $result = $importer->store($request->input('rows', []), $userId);

        return response()->json($result);
    }

    private function resolveImporter(
        string $importType,
        FastagStatementImporter $fastagImporter,
        FuelStatementImporter $fuelImporter,
    ): FastagStatementImporter|FuelStatementImporter {
        return $importType === StatementColumnMapper::TYPE_FUEL
            ? $fuelImporter
            : $fastagImporter;
    }
}
