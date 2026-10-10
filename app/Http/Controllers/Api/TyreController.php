<?php

namespace App\Http\Controllers\Api;

use App\Branch;
use App\Enums\TyreType;
use App\Http\Controllers\Controller;
use App\Http\Requests\InstallTyreRequest;
use App\Http\Requests\RemoveTyreInstallationRequest;
use App\Http\Requests\ScrapTyreRequest;
use App\Http\Requests\StoreTyreDamageRequest;
use App\Http\Requests\StoreTyreExpenseRequest;
use App\Http\Requests\StoreTyreInspectionRequest;
use App\Http\Requests\StoreTyreReplacementRequest;
use App\Http\Requests\StoreTyreRequest;
use App\Http\Requests\StoreTyreRotationRequest;
use App\Http\Requests\UpdateTyreRequest;
use App\Http\Requests\UpdateTyreStintRequest;
use App\Http\Resources\TyreDamageResource;
use App\Http\Resources\TyreExpenseResource;
use App\Http\Resources\TyreInspectionResource;
use App\Http\Resources\TyreInstallationResource;
use App\Http\Resources\TyreResource;
use App\Http\Resources\TyreRotationResource;
use App\Modules\TyreManagement\Services\TyreAlertService;
use App\Modules\TyreManagement\Services\TyreService;
use App\Services\BulkUploadService;
use App\Support\BranchContext;
use App\Support\CurrentTenant;
use App\Support\TyreLifeCalculator;
use App\Tyre;
use App\TyreInstallation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TyreController extends Controller
{
    public function __construct(
        private readonly TyreService $tyreService,
        private readonly TyreAlertService $tyreAlertService,
    ) {}

    public function bulkUploadTemplate(BulkUploadService $bulkUploadService): StreamedResponse
    {
        $this->authorize('viewAny', Tyre::class);

        return $bulkUploadService->downloadTemplate(
            headers: [
                'Tyre Serial Number',
                'Brand',
                'Size',
                'Purchase Date',
                'Purchase Cost',
                'Expected Life KM',
                'Tyre Type',
                'Warranty Valid Until',
                'Warranty KM',
                'Current KM',
                'Notes',
            ],
            fileName: 'tyres_bulk_upload_template.csv'
        );
    }

    public function bulkUpload(Request $request, BulkUploadService $bulkUploadService): JsonResponse
    {
        $this->authorize('create', Tyre::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'extensions:csv,xlsx,xls', 'max:5120'],
        ], [
            'file.mimes' => 'The uploaded file must be a CSV or Excel file (.csv, .xlsx, .xls).',
            'file.extensions' => 'The uploaded file must be a CSV or Excel file (.csv, .xlsx, .xls).',
        ]);

        $tenantId = CurrentTenant::requireId();
        $branchId = BranchContext::branchId()
            ?? Branch::query()->where('tenant_id', $tenantId)->first()?->id;

        $rowValidator = function (array &$rowData, int $rowNumber) use ($tenantId): array {
            $errors = [];

            // 1. Normalize and validate Serial Number
            $serialRaw = trim((string) ($rowData['serial_number'] ?? ''));
            if ($serialRaw === '') {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'Tyre Serial Number',
                    'value' => '',
                    'message' => 'Tyre serial number is required.',
                ];
            } else {
                $normalizedSerial = strtoupper($serialRaw);
                $rowData['serial_number'] = $normalizedSerial;

                $exists = Tyre::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('serial_number', $normalizedSerial)
                    ->exists();

                if ($exists) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'Tyre Serial Number',
                        'value' => $serialRaw,
                        'message' => sprintf('Tyre serial number "%s" is already registered for your company.', $serialRaw),
                    ];
                }
            }

            // 2. Validate Purchase Date
            $purchaseDateRaw = trim((string) ($rowData['purchase_date'] ?? ''));
            if ($purchaseDateRaw === '') {
                $errors[] = [
                    'row' => $rowNumber,
                    'field' => 'Purchase Date',
                    'value' => '',
                    'message' => 'Purchase date is required.',
                ];
            } else {
                $purchaseTime = strtotime($purchaseDateRaw);
                if ($purchaseTime === false) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'Purchase Date',
                        'value' => $purchaseDateRaw,
                        'message' => 'Invalid purchase date format.',
                    ];
                } else {
                    $purchaseFormatted = date('Y-m-d', $purchaseTime);
                    if ($purchaseFormatted > date('Y-m-d')) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => 'Purchase Date',
                            'value' => $purchaseDateRaw,
                            'message' => 'Purchase date cannot be in the future.',
                        ];
                    } else {
                        $rowData['purchase_date'] = $purchaseFormatted;
                    }
                }
            }

            // 3. Normalize Tyre Type
            $typeRaw = trim((string) ($rowData['tyre_type'] ?? ''));
            if ($typeRaw !== '') {
                $typeUpper = strtoupper($typeRaw);
                $validTypes = array_column(TyreType::cases(), 'value');
                if (! in_array($typeUpper, $validTypes, true)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'Tyre Type',
                        'value' => $typeRaw,
                        'message' => sprintf('Invalid tyre type "%s". Allowed: %s.', $typeRaw, implode(', ', $validTypes)),
                    ];
                } else {
                    $rowData['tyre_type'] = $typeUpper;
                }
            } else {
                $rowData['tyre_type'] = TyreType::Radial->value;
            }

            // 4. Validate Warranty Valid Until
            $warrantyRaw = trim((string) ($rowData['warranty_valid_until'] ?? ''));
            if ($warrantyRaw !== '') {
                $warrantyTime = strtotime($warrantyRaw);
                if ($warrantyTime === false) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'Warranty Valid Until',
                        'value' => $warrantyRaw,
                        'message' => 'Invalid warranty valid until date format.',
                    ];
                } else {
                    $warrantyFormatted = date('Y-m-d', $warrantyTime);
                    if (! empty($rowData['purchase_date']) && $warrantyFormatted < $rowData['purchase_date']) {
                        $errors[] = [
                            'row' => $rowNumber,
                            'field' => 'Warranty Valid Until',
                            'value' => $warrantyRaw,
                            'message' => 'Warranty valid date must be on or after purchase date.',
                        ];
                    } else {
                        $rowData['warranty_valid_until'] = $warrantyFormatted;
                    }
                }
            } else {
                $rowData['warranty_valid_until'] = null;
            }

            // 5. Expected Life KM
            $expectedLifeRaw = trim((string) ($rowData['expected_life_km'] ?? ''));
            if ($expectedLifeRaw === '') {
                $rowData['expected_life_km'] = TyreLifeCalculator::DEFAULT_EXPECTED_LIFE_KM;
            } else {
                $expectedLife = (float) $expectedLifeRaw;
                if ($expectedLife < 1000) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'Expected Life KM',
                        'value' => $expectedLifeRaw,
                        'message' => 'Expected life must be at least 1,000 KM.',
                    ];
                } else {
                    $rowData['expected_life_km'] = $expectedLife;
                }
            }

            // 6. Current KM
            $currentKmRaw = trim((string) ($rowData['current_km'] ?? ''));
            if ($currentKmRaw !== '') {
                $currentKm = (float) $currentKmRaw;
                if ($currentKm < 0) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'Current KM',
                        'value' => $currentKmRaw,
                        'message' => 'Current KM cannot be negative.',
                    ];
                } else {
                    $rowData['current_km'] = $currentKm;
                }
            } else {
                $rowData['current_km'] = 0;
            }

            // 7. Purchase Cost
            $costRaw = trim((string) ($rowData['purchase_cost'] ?? ''));
            if ($costRaw !== '') {
                $cost = (float) $costRaw;
                if ($cost < 0) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'Purchase Cost',
                        'value' => $costRaw,
                        'message' => 'Purchase cost cannot be negative.',
                    ];
                } else {
                    $rowData['purchase_cost'] = $cost;
                }
            } else {
                $rowData['purchase_cost'] = 0;
            }

            // 8. Warranty KM
            $warrantyKmRaw = trim((string) ($rowData['warranty_km'] ?? ''));
            if ($warrantyKmRaw !== '') {
                $wKm = (float) $warrantyKmRaw;
                if ($wKm < 0) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'Warranty KM',
                        'value' => $warrantyKmRaw,
                        'message' => 'Warranty KM cannot be negative.',
                    ];
                } else {
                    $rowData['warranty_km'] = $wKm;
                }
            } else {
                $rowData['warranty_km'] = null;
            }

            return $errors;
        };

        $saveHandler = function (array $data) use ($tenantId, $branchId): Tyre {
            $data['tenant_id'] = $tenantId;
            if (! empty($branchId) && empty($data['branch_id'])) {
                $data['branch_id'] = $branchId;
            }

            return $this->tyreService->create($data);
        };

        $result = $bulkUploadService->process(
            file: $request->file('file'),
            modelClass: Tyre::class,
            columnSpecs: [
                'serial_number' => [
                    'label' => 'Tyre Serial Number',
                    'required' => true,
                    'aliases' => ['serial_number', 'serial_no', 'tyre_serial', 'tyre_number', 'tyre_no', 'serial'],
                    'rules' => ['required', 'string', 'max:80'],
                    'unique' => true,
                ],
                'brand' => [
                    'label' => 'Brand',
                    'required' => true,
                    'aliases' => ['brand', 'make', 'tyre_brand', 'company'],
                    'rules' => ['required', 'string', 'max:100'],
                ],
                'size' => [
                    'label' => 'Size',
                    'required' => true,
                    'aliases' => ['size', 'tyre_size', 'dimension'],
                    'rules' => ['required', 'string', 'max:50'],
                ],
                'purchase_date' => [
                    'label' => 'Purchase Date',
                    'required' => true,
                    'aliases' => ['purchase_date', 'date_of_purchase', 'purchase_dt'],
                    'rules' => ['required', 'date'],
                ],
                'purchase_cost' => [
                    'label' => 'Purchase Cost',
                    'required' => false,
                    'aliases' => ['purchase_cost', 'cost', 'price', 'purchase_price', 'amount'],
                    'rules' => ['nullable', 'numeric', 'min:0'],
                ],
                'expected_life_km' => [
                    'label' => 'Expected Life KM',
                    'required' => false,
                    'aliases' => ['expected_life_km', 'expected_life', 'life_km', 'expected_km'],
                    'rules' => ['nullable', 'numeric', 'min:1000'],
                ],
                'tyre_type' => [
                    'label' => 'Tyre Type',
                    'required' => false,
                    'aliases' => ['tyre_type', 'type'],
                    'rules' => ['nullable', 'string'],
                ],
                'warranty_valid_until' => [
                    'label' => 'Warranty Valid Until',
                    'required' => false,
                    'aliases' => ['warranty_valid_until', 'warranty_date', 'warranty_expiry'],
                    'rules' => ['nullable', 'date'],
                ],
                'warranty_km' => [
                    'label' => 'Warranty KM',
                    'required' => false,
                    'aliases' => ['warranty_km', 'warranty_kilometers'],
                    'rules' => ['nullable', 'numeric', 'min:0'],
                ],
                'current_km' => [
                    'label' => 'Current KM',
                    'required' => false,
                    'aliases' => ['current_km', 'initial_km', 'odometer_km', 'km'],
                    'rules' => ['nullable', 'numeric', 'min:0'],
                ],
                'notes' => [
                    'label' => 'Notes',
                    'required' => false,
                    'aliases' => ['notes', 'note', 'remark', 'remarks', 'description'],
                    'rules' => ['nullable', 'string', 'max:2000'],
                ],
            ],
            rowValidator: $rowValidator,
            saveHandler: $saveHandler
        );

        return response()->json($result);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tyre::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $search = $request->filled('search') ? (string) $request->input('search') : null;
        $status = $request->filled('status') ? (string) $request->input('status') : null;
        $vehicleId = $request->filled('vehicle_id') ? (string) $request->input('vehicle_id') : null;

        return TyreResource::collection($this->tyreService->paginate($perPage, $search, $status, $vehicleId));
    }

    public function store(StoreTyreRequest $request): TyreResource
    {
        $tyre = $this->tyreService->create($request->validated());

        return new TyreResource($tyre);
    }

    public function show(Tyre $tyre): JsonResponse
    {
        $this->authorize('view', $tyre);

        $payload = $this->tyreService->showWithLifecycle($tyre->id);

        return response()->json([
            'data' => (new TyreResource($payload['tyre']))->resolve(),
            'life_summary' => $payload['life_summary'],
            'timeline' => $payload['timeline'],
        ]);
    }

    public function removalPreview(Request $request, TyreInstallation $tyreInstallation): JsonResponse
    {
        $this->authorize('view', $tyreInstallation->tyre);

        $stintKm = $request->filled('stint_km') ? (float) $request->input('stint_km') : null;
        $odometerKm = $request->filled('odometer_km') ? (float) $request->input('odometer_km') : null;

        return response()->json([
            'data' => $this->tyreService->previewRemoval($tyreInstallation, $stintKm, $odometerKm),
            'life_summary' => $this->tyreService->showWithLifecycle($tyreInstallation->tyre_id)['life_summary'],
        ]);
    }

    public function update(UpdateTyreRequest $request, Tyre $tyre): TyreResource
    {
        $tyre = $this->tyreService->update($tyre, $request->validated());

        return new TyreResource($tyre);
    }

    public function destroy(Tyre $tyre): Response
    {
        $this->authorize('delete', $tyre);
        $this->tyreService->delete($tyre);

        return response()->noContent();
    }

    public function alerts(): JsonResponse
    {
        $this->authorize('viewAny', Tyre::class);

        return response()->json([
            'data' => $this->tyreAlertService->alerts(),
        ]);
    }

    public function install(InstallTyreRequest $request, Tyre $tyre): TyreInstallationResource
    {
        $installation = $this->tyreService->install($tyre, $request->validated());

        return new TyreInstallationResource($installation);
    }

    public function removeInstallation(RemoveTyreInstallationRequest $request, TyreInstallation $tyreInstallation): TyreInstallationResource
    {
        $installation = $this->tyreService->removeInstallation($tyreInstallation, $request->validated());

        return new TyreInstallationResource($installation);
    }

    public function updateStintKm(UpdateTyreStintRequest $request, TyreInstallation $tyreInstallation): TyreInstallationResource
    {
        $installation = $this->tyreService->updateStintKm($tyreInstallation, $request->validated());

        return new TyreInstallationResource($installation);
    }

    public function rotate(StoreTyreRotationRequest $request, Tyre $tyre): TyreRotationResource
    {
        $rotation = $this->tyreService->rotate($tyre, $request->validated());

        return new TyreRotationResource($rotation);
    }

    public function damage(StoreTyreDamageRequest $request, Tyre $tyre): TyreDamageResource
    {
        $damage = $this->tyreService->recordDamage($tyre, $request->validated());

        return new TyreDamageResource($damage);
    }

    public function replace(StoreTyreReplacementRequest $request): JsonResponse
    {
        $replacement = $this->tyreService->replaceOnVehicle($request->validated());

        return response()->json([
            'data' => [
                'id' => $replacement->id,
                'vehicle_id' => $replacement->vehicle_id,
                'old_tyre_id' => $replacement->old_tyre_id,
                'new_tyre_id' => $replacement->new_tyre_id,
                'replaced_at' => $replacement->replaced_at?->toDateString(),
            ],
        ], 201);
    }

    public function inspection(StoreTyreInspectionRequest $request, Tyre $tyre): TyreInspectionResource
    {
        $inspection = $this->tyreService->recordInspection($tyre, $request->validated());

        return new TyreInspectionResource($inspection);
    }

    public function scrap(ScrapTyreRequest $request, Tyre $tyre): TyreResource
    {
        $tyre = $this->tyreService->scrap($tyre, $request->validated());

        return new TyreResource($tyre);
    }

    public function expense(StoreTyreExpenseRequest $request, Tyre $tyre): TyreExpenseResource
    {
        $expense = $this->tyreService->recordMaintenanceExpense($tyre, $request->validated());

        return new TyreExpenseResource($expense);
    }

    public function vehiclePositions(Request $request, string $vehicle): JsonResponse
    {
        $this->authorize('viewAny', Tyre::class);

        return response()->json([
            'data' => $this->tyreService->vehiclePositions($vehicle),
        ]);
    }

    public function vehicleTyreHistory(Request $request, string $vehicle): JsonResponse
    {
        $this->authorize('viewAny', Tyre::class);

        return response()->json($this->tyreService->vehicleTyreHistory($vehicle));
    }
}
