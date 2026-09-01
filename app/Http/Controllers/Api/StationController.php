<?php

namespace App\Http\Controllers\Api;

use App\Exports\StationsTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStationRequest;
use App\Http\Requests\UpdateStationRequest;
use App\Http\Resources\StationResource;
use App\Imports\StationsBulkImport;
use App\Modules\TripManagement\Services\StationService;
use App\Station;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class StationController extends Controller
{
    public function __construct(private readonly StationService $stationService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Station::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);
        $search = $request->filled('search') ? (string) $request->input('search') : null;

        if ($perPage >= 500) {
            return StationResource::collection($this->stationService->listAll($search));
        }

        return StationResource::collection($this->stationService->paginate($perPage, $search));
    }

    public function store(StoreStationRequest $request): StationResource
    {
        $station = $this->stationService->create($request->validated());

        return new StationResource($station);
    }

    public function show(Station $station): StationResource
    {
        $this->authorize('view', $station);

        return new StationResource($station);
    }

    public function update(UpdateStationRequest $request, Station $station): StationResource
    {
        $station = $this->stationService->update($station, $request->validated());

        return new StationResource($station);
    }

    public function destroy(Request $request, Station $station): Response
    {
        $this->authorize('delete', $station);

        $validated = $request->validate([
            'deleted_reason' => ['required', 'string', 'max:1000'],
        ]);
        

        $this->stationService->delete(
            $station,
            $request->user()?->id,
            $validated['deleted_reason'],
        );

        return response()->noContent();
    }

    public function downloadBulkTemplate()
{
    $this->authorize('create', Station::class);

    return Excel::download(
        new StationsTemplateExport(),
        'stations_template.xlsx'
    );
}

public function bulkUpload(Request $request): JsonResponse
{
    $this->authorize('create', Station::class);

    $request->validate([
        'file' => [
            'required',
            'file',
            'mimes:xlsx,xls,csv',
            'max:10240',
        ],
    ]);

    $import = new StationsBulkImport(
        (string) CurrentTenant::id(),
        $request->user()?->id,
    );

    Excel::import($import, $request->file('file'));

    return response()->json([
        'message' => 'Bulk upload completed.',
        'total' => $import->total,
        'successful' => $import->successful,
        'failed' => $import->failed,
        'errors' => $import->errors,
    ]);
}
}
