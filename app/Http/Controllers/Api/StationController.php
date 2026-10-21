<?php

namespace App\Http\Controllers\Api;

use App\City;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStationRequest;
use App\Http\Requests\UpdateStationRequest;
use App\Http\Resources\StationResource;
use App\Modules\TripManagement\Services\StationService;
use App\Services\BulkUploadService;
use App\State;
use App\Station;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StationController extends Controller
{
    public function __construct(private readonly StationService $stationService)
    {
    }

    public function bulkUploadTemplate(BulkUploadService $bulkUploadService): StreamedResponse
    {
        $this->authorize('viewAny', Station::class);

        return $bulkUploadService->downloadTemplate(
            headers: [
                'Station Name',
                'State',
                'City',
            ],
            fileName: 'stations_bulk_upload_template.xlsx',
            requiredHeaders: [
                'Station Name',
                'State',
                'City',
            ],
        );
    }

    public function bulkUpload(Request $request, BulkUploadService $bulkUploadService): JsonResponse
    {
        $this->authorize('create', Station::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'extensions:csv,xlsx,xls', 'max:5120'],
        ], [
            'file.mimes' => 'The uploaded file must be a CSV or Excel file (.csv, .xlsx, .xls).',
            'file.extensions' => 'The uploaded file must be a CSV or Excel file (.csv, .xlsx, .xls).',
        ]);

        $tenantId = CurrentTenant::requireId();

        $statesByName = [];
        $statesByCode = [];
        $statesById = [];
        foreach (State::all() as $st) {
            $statesByName[mb_strtolower($st->name, 'UTF-8')] = $st;
            if ($st->code) {
                $statesByCode[mb_strtolower($st->code, 'UTF-8')] = $st;
            }
            $statesById[$st->id] = $st;
        }

        $citiesCache = [];

        $rowValidator = function (array &$rowData, int $rowNumber) use (
            &$statesByName,
            &$statesByCode,
            &$statesById,
            &$citiesCache
        ): array {
            $errors = [];

            // 1. Resolve State
            $stateRaw = trim((string) ($rowData['state'] ?? ''));
            $stateLower = mb_strtolower($stateRaw, 'UTF-8');
            $resolvedState = $statesByName[$stateLower]
                ?? ($statesByCode[$stateLower] ?? ($statesById[(int) $stateRaw] ?? null));

            if (! $resolvedState) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'State',
                    'value' => $stateRaw,
                    'message' => sprintf('State "%s" was not found in registered states.', $stateRaw),
                ];

                return $errors;
            }

            $rowData['state_id'] = $resolvedState->id;
            $rowData['country_id'] = $resolvedState->country_id;

            // 2. Resolve City
            $cityRaw = trim((string) ($rowData['city'] ?? ''));
            $cityLower = mb_strtolower($cityRaw, 'UTF-8');

            if (! isset($citiesCache[$resolvedState->id])) {
                $citiesCache[$resolvedState->id] = [];
            }

            if (isset($citiesCache[$resolvedState->id][$cityLower])) {
                $existingCity = $citiesCache[$resolvedState->id][$cityLower];
            } else {
                $existingCity = City::query()
                    ->where('state_id', $resolvedState->id)
                    ->where(function ($q) use ($cityLower, $cityRaw) {
                        $q->whereRaw('LOWER(name) = ?', [$cityLower]);
                        if (is_numeric($cityRaw)) {
                            $q->orWhere('id', (int) $cityRaw);
                        }
                    })
                    ->first();

                if ($existingCity) {
                    $citiesCache[$resolvedState->id][$cityLower] = $existingCity;
                }
            }

            if (! $existingCity) {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'City',
                    'value' => $cityRaw,
                    'message' => sprintf('City "%s" was not found in city records for state "%s".', $cityRaw, $resolvedState->name),
                ];

                return $errors;
            }

            $rowData['city_id'] = $existingCity->id;

            return $errors;
        };

        $saveHandler = function (array $data) use ($tenantId): Station {
            return Station::create([
                'tenant_id' => $tenantId,
                'country_id' => $data['country_id'] ?? null,
                'state_id' => $data['state_id'],
                'city_id' => $data['city_id'],
                'name' => $data['name'],
            ]);
        };

        $result = $bulkUploadService->process(
            file: $request->file('file'),
            modelClass: Station::class,
            columnSpecs: [
                'name' => [
                    'label' => 'Station Name',
                    'required' => true,
                    'aliases' => ['name', 'station_name', 'station'],
                    'rules' => ['required', 'string', 'max:150'],
                    'unique' => true,
                ],
                'state' => [
                    'label' => 'State',
                    'required' => true,
                    'aliases' => ['state', 'state_name', 'state_code'],
                    'rules' => ['required', 'string', 'max:255'],
                ],
                'city' => [
                    'label' => 'City',
                    'required' => true,
                    'aliases' => ['city', 'city_name'],
                    'rules' => ['required', 'string', 'max:255'],
                ],
            ],
            rowValidator: $rowValidator,
            saveHandler: $saveHandler,
        );

        return response()->json($result);
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
}
