<?php

namespace App\Http\Controllers\Api;

use App\Driver;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDriverRequest;
use App\Http\Requests\UpdateDriverRequest;
use App\Http\Resources\DriverResource;
use App\Modules\DriverManagement\Services\DriverService;
use App\Services\BulkUploadService;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriverController extends Controller
{
    public function __construct(private readonly DriverService $driverService) {}

    public function bulkUploadTemplate(BulkUploadService $bulkUploadService): StreamedResponse
    {
        $this->authorize('viewAny', Driver::class);

        return $bulkUploadService->downloadTemplate(
            headers: [
                'Driver Name',
                'Phone',
                'License Number',
                'Joining Date',
                'Monthly Salary',
                'Address',
                'Aadhaar Number',
                'PAN Number',
                'License Expiry Date',
                'Bank Account Number',
                'Bank IFSC',
                'Emergency Contact Name',
                'Emergency Contact Phone',
            ],
            fileName: 'drivers_bulk_upload_template.xlsx',
            requiredHeaders: [
                'Driver Name',
                'Phone',
                'License Number',
                'Joining Date',
                'Monthly Salary',
            ],
        );
    }

    public function bulkUpload(Request $request, BulkUploadService $bulkUploadService): JsonResponse
    {
        $this->authorize('create', Driver::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'extensions:csv,xlsx,xls', 'max:5120'],
        ], [
            'file.mimes' => 'The uploaded file must be a CSV or Excel file (.csv, .xlsx, .xls).',
            'file.extensions' => 'The uploaded file must be a CSV or Excel file (.csv, .xlsx, .xls).',
        ]);

        $tenantId = CurrentTenant::requireId();
        $userId = $request->user()?->id;

        $rowValidator = function (array &$rowData, int $rowNumber) use ($tenantId): array {
            $errors = [];

            // 1. Normalize dates
            if (! empty($rowData['joining_date'])) {
                $joiningTime = strtotime((string) $rowData['joining_date']);
                if ($joiningTime === false) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'Joining Date',
                        'value' => (string) $rowData['joining_date'],
                        'message' => 'Invalid joining date format.',
                    ];
                } else {
                    $rowData['joining_date'] = date('Y-m-d', $joiningTime);
                }
            }

            if (! empty($rowData['license_expiry_date'])) {
                $expiryTime = strtotime((string) $rowData['license_expiry_date']);
                if ($expiryTime === false) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'License Expiry Date',
                        'value' => (string) $rowData['license_expiry_date'],
                        'message' => 'Invalid license expiry date format.',
                    ];
                } else {
                    $rowData['license_expiry_date'] = date('Y-m-d', $expiryTime);
                }
            } else {
                $rowData['license_expiry_date'] = null;
            }

            // 2. Normalize and check uniqueness for Phone & License Number
            $phoneRaw = trim((string) ($rowData['phone'] ?? ''));
            $phoneDigits = preg_replace('/\D+/', '', $phoneRaw);
            if ($phoneDigits !== '') {
                $rowData['phone'] = $phoneDigits;
                $existsPhone = Driver::query()
                    ->where('tenant_id', $tenantId)
                    ->where('phone', $phoneDigits)
                    ->exists();

                if ($existsPhone) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'Phone',
                        'value' => $phoneRaw,
                        'message' => 'This phone number is already registered for another driver in your company.',
                    ];
                }
            }

            $licenseRaw = trim((string) ($rowData['license_number'] ?? ''));
            if ($licenseRaw !== '') {
                $existsLicense = Driver::query()
                    ->where('tenant_id', $tenantId)
                    ->where('license_number', $licenseRaw)
                    ->exists();

                if ($existsLicense) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'License Number',
                        'value' => $licenseRaw,
                        'message' => 'This license number is already registered for another driver in your company.',
                    ];
                }
            }

            // 3. Normalize and check uniqueness for Aadhaar / PAN
            $aadhaarRaw = trim((string) ($rowData['aadhaar_number'] ?? ''));
            $rowData['aadhaar_number'] = $aadhaarRaw !== '' ? preg_replace('/\D/', '', $aadhaarRaw) : null;
            if ($rowData['aadhaar_number']) {
                $existsAadhaar = Driver::query()
                    ->where('tenant_id', $tenantId)
                    ->where('aadhaar_number', $rowData['aadhaar_number'])
                    ->exists();

                if ($existsAadhaar) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'Aadhaar Number',
                        'value' => $rowData['aadhaar_number'],
                        'message' => 'This Aadhaar number is already registered for another driver in your company.',
                    ];
                }
            }

            $panRaw = trim((string) ($rowData['pan_number'] ?? ''));
            $rowData['pan_number'] = $panRaw !== '' ? strtoupper($panRaw) : null;
            if ($rowData['pan_number']) {
                $existsPan = Driver::query()
                    ->where('tenant_id', $tenantId)
                    ->where('pan_number', $rowData['pan_number'])
                    ->exists();

                if ($existsPan) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'PAN Number',
                        'value' => $rowData['pan_number'],
                        'message' => 'This PAN number is already registered for another driver in your company.',
                    ];
                }
            }

            // 3. Clean string / null fields
            foreach (['address', 'bank_account_number', 'bank_ifsc', 'emergency_contact_name', 'emergency_contact_phone'] as $f) {
                $val = trim((string) ($rowData[$f] ?? ''));
                $rowData[$f] = $val !== '' ? $val : null;
            }

            return $errors;
        };

        $saveHandler = function (array $data) use ($tenantId, $userId): Driver {
            return Driver::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'license_number' => $data['license_number'],
                'license_expiry_date' => $data['license_expiry_date'] ?? null,
                'address' => $data['address'] ?? null,
                'aadhaar_number' => $data['aadhaar_number'] ?? null,
                'pan_number' => $data['pan_number'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'bank_ifsc' => $data['bank_ifsc'] ?? null,
                'joining_date' => $data['joining_date'],
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'daily_wage' => $data['daily_wage'],
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        };

        $result = $bulkUploadService->process(
            file: $request->file('file'),
            modelClass: Driver::class,
            columnSpecs: [
                'name' => [
                    'label' => 'Driver Name',
                    'required' => true,
                    'aliases' => ['driver_name', 'name', 'driver'],
                    'rules' => ['required', 'string', 'max:150'],
                ],
                'phone' => [
                    'label' => 'Phone',
                    'required' => true,
                    'aliases' => ['phone', 'mobile', 'mobile_no', 'contact_no', 'phone_number'],
                    'rules' => ['required', 'string', 'digits:10', 'regex:/^[6-9]\d{9}$/'],
                    'unique' => true,
                ],
                'license_number' => [
                    'label' => 'License Number',
                    'required' => true,
                    'aliases' => ['license_number', 'license_no', 'license', 'dl_number', 'dl_no'],
                    'rules' => ['required', 'string', 'max:50'],
                    'unique' => true,
                ],
                'joining_date' => [
                    'label' => 'Joining Date',
                    'required' => true,
                    'aliases' => ['joining_date', 'joined_date', 'doj'],
                    'rules' => ['required', 'date'],
                ],
                'daily_wage' => [
                    'label' => 'Monthly Salary',
                    'required' => true,
                    'aliases' => ['monthly_salary', 'salary', 'monthly_wages', 'daily_wage', 'daily_wages', 'wage'],
                    'rules' => ['required', 'numeric', 'gt:0'],
                ],
                'address' => [
                    'label' => 'Address',
                    'required' => false,
                    'aliases' => ['address', 'driver_address'],
                    'rules' => ['nullable', 'string', 'max:500'],
                ],
                'aadhaar_number' => [
                    'label' => 'Aadhaar Number',
                    'required' => false,
                    'aliases' => ['aadhaar_number', 'aadhaar', 'aadhaar_no', 'aadhar'],
                    'rules' => ['nullable', 'string', 'digits:12'],
                    'unique' => true,
                ],
                'pan_number' => [
                    'label' => 'PAN Number',
                    'required' => false,
                    'aliases' => ['pan_number', 'pan', 'pan_no'],
                    'rules' => ['nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/i'],
                    'unique' => true,
                ],
                'license_expiry_date' => [
                    'label' => 'License Expiry Date',
                    'required' => false,
                    'aliases' => ['license_expiry_date', 'expiry_date', 'dl_expiry'],
                    'rules' => ['nullable', 'date'],
                ],
                'bank_account_number' => [
                    'label' => 'Bank Account Number',
                    'required' => false,
                    'aliases' => ['bank_account_number', 'account_number', 'account_no'],
                    'rules' => ['nullable', 'string', 'max:40'],
                ],
                'bank_ifsc' => [
                    'label' => 'Bank IFSC',
                    'required' => false,
                    'aliases' => ['bank_ifsc', 'ifsc', 'ifsc_code'],
                    'rules' => ['nullable', 'string', 'max:20'],
                ],
                'emergency_contact_name' => [
                    'label' => 'Emergency Contact Name',
                    'required' => false,
                    'aliases' => ['emergency_contact_name', 'emergency_name'],
                    'rules' => ['nullable', 'string', 'max:150'],
                ],
                'emergency_contact_phone' => [
                    'label' => 'Emergency Contact Phone',
                    'required' => false,
                    'aliases' => ['emergency_contact_phone', 'emergency_phone'],
                    'rules' => ['nullable', 'string', 'digits:10'],
                ],
            ],
            rowValidator: $rowValidator,
            saveHandler: $saveHandler,
        );

        return response()->json($result);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Driver::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);
        $activeOnly = $request->boolean('active_only', false);
        $search = $request->filled('search') ? $request->string('search')->toString() : null;
        $availableOnDate = $request->filled('available_on_date') ? $request->string('available_on_date')->toString() : null;
        $excludeVehicleId = $request->filled('exclude_vehicle_id') ? $request->string('exclude_vehicle_id')->toString() : null;
        $excludeAssignmentId = $request->filled('exclude_assignment_id') ? $request->string('exclude_assignment_id')->toString() : null;

        $drivers = $this->driverService->paginate($perPage, $activeOnly, $search, $availableOnDate, $excludeVehicleId, $excludeAssignmentId);
        $drivers->load(['currentPrimaryAssignment.vehicle', 'createdBy', 'updatedBy']);

        return DriverResource::collection($drivers);
    }

    public function store(StoreDriverRequest $request): DriverResource
    {
        $driver = $this->driverService->create([
            ...$request->validated(),
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $driver->load(['currentPrimaryAssignment.vehicle', 'createdBy', 'updatedBy']);

        return new DriverResource($driver);
    }

    public function show(Driver $driver): DriverResource
    {
        $this->authorize('view', $driver);

        $driver->load(['currentPrimaryAssignment.vehicle', 'createdBy', 'updatedBy']);

        return new DriverResource($driver);
    }

    public function update(UpdateDriverRequest $request, Driver $driver): DriverResource
    {
        $driver = $this->driverService->update($driver, [
            ...$request->validated(),
            'updated_by' => $request->user()?->id,
        ]);

        $driver->load(['currentPrimaryAssignment.vehicle', 'createdBy', 'updatedBy']);

        return new DriverResource($driver);
    }

    public function destroy(Request $request, Driver $driver): Response
    {
        $this->authorize('delete', $driver);
    
        $validated = $request->validate([
            'deleted_reason' => ['required', 'string', 'max:1000'],
        ]);
    
        $this->driverService->delete(
            $driver,
            $request->user()?->id,
            $validated['deleted_reason'],
        );
    
        return response()->noContent();
    }

    public function licenseExpiring(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Driver::class);

        $days = (int) $request->integer('days', 30);
        $days = max(1, min($days, 365));

        return DriverResource::collection($this->driverService->licenseExpiringWithinDays($days));
    }
}
