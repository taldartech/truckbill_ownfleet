<?php

namespace App\Http\Controllers\Api;

use App\City;
use App\Customer;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Modules\TripManagement\Services\CustomerService;
use App\Services\BulkUploadService;
use App\State;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customerService) {}

    public function bulkUploadTemplate(BulkUploadService $bulkUploadService): StreamedResponse
    {
        $this->authorize('viewAny', Customer::class);

        return $bulkUploadService->downloadTemplate(
            headers: [
                'Customer Name',
                'Mobile',
                'State',
                'City',
                'Address',
                'GST Number',
                'PAN Number',
            ],
            fileName: 'customers_bulk_upload_template.csv'
        );
    }

    public function bulkUpload(Request $request, BulkUploadService $bulkUploadService): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'extensions:csv,xlsx,xls', 'max:5120'],
        ], [
            'file.mimes' => 'The uploaded file must be a CSV or Excel file (.csv, .xlsx, .xls).',
            'file.extensions' => 'The uploaded file must be a CSV or Excel file (.csv, .xlsx, .xls).',
        ]);

        $tenantId = CurrentTenant::requireId();
        $userId = $request->user()?->id;

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
            $tenantId,
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

            // 3. Normalize GST and PAN
            $gstRaw = trim((string) ($rowData['gst_number'] ?? ''));
            $rowData['gst_number'] = $gstRaw !== '' ? strtoupper($gstRaw) : null;

            $panRaw = trim((string) ($rowData['pan_number'] ?? ''));
            $rowData['pan_number'] = $panRaw !== '' ? strtoupper($panRaw) : null;

            if ($rowData['gst_number']) {
                $existsGst = Customer::query()
                    ->where('tenant_id', $tenantId)
                    ->where('gst_number', $rowData['gst_number'])
                    ->exists();

                if ($existsGst) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'GST Number',
                        'value' => $rowData['gst_number'],
                        'message' => 'This GST number is already registered for another customer in your company.',
                    ];
                }
            }

            if ($rowData['pan_number']) {
                $existsPan = Customer::query()
                    ->where('tenant_id', $tenantId)
                    ->where('pan_number', $rowData['pan_number'])
                    ->exists();

                if ($existsPan) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'field' => 'PAN Number',
                        'value' => $rowData['pan_number'],
                        'message' => 'This PAN number is already registered for another customer in your company.',
                    ];
                }
            }

            return $errors;
        };

        $saveHandler = function (array $data) use ($tenantId, $userId): Customer {
            return Customer::create([
                'tenant_id' => $tenantId,
                'country_id' => $data['country_id'] ?? null,
                'state_id' => $data['state_id'],
                'city_id' => $data['city_id'],
                'name' => $data['name'],
                'contact_number' => $data['contact_number'],
                'address' => ! empty($data['address']) ? $data['address'] : null,
                'gst_number' => $data['gst_number'] ?? null,
                'pan_number' => $data['pan_number'] ?? null,
                'is_broker' => false,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        };

        $result = $bulkUploadService->process(
            file: $request->file('file'),
            modelClass: Customer::class,
            columnSpecs: [
                'name' => [
                    'label' => 'Customer Name',
                    'required' => true,
                    'aliases' => ['name', 'customer_name', 'customer', 'party_name', 'party'],
                    'rules' => ['required', 'string', 'max:255'],
                ],
                'contact_number' => [
                    'label' => 'Mobile',
                    'required' => true,
                    'aliases' => ['mobile', 'contact_number', 'phone', 'contact_no', 'mobile_no'],
                    'rules' => ['required', 'string', 'digits:10', 'regex:/^[6-9]\d{9}$/'],
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
                'address' => [
                    'label' => 'Address',
                    'required' => false,
                    'aliases' => ['address', 'customer_address'],
                    'rules' => ['nullable', 'string', 'max:2000'],
                ],
                'gst_number' => [
                    'label' => 'GST Number',
                    'required' => false,
                    'aliases' => ['gst_number', 'gst', 'gst_no', 'gstin'],
                    'rules' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/i'],
                ],
                'pan_number' => [
                    'label' => 'PAN Number',
                    'required' => false,
                    'aliases' => ['pan_number', 'pan', 'pan_no'],
                    'rules' => ['nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/i'],
                ],
            ],
            rowValidator: $rowValidator,
            saveHandler: $saveHandler,
        );

        return response()->json($result);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $perPage = min(max((int) $request->input('per_page', 15), 1), 500);
        $search = $request->filled('search') ? (string) $request->input('search') : null;

        if ($perPage >= 500) {
            return CustomerResource::collection($this->customerService->listAll($search));
        }

        return CustomerResource::collection($this->customerService->paginate($perPage, $search));
    }

    public function store(StoreCustomerRequest $request): CustomerResource
    {
        $customer = $this->customerService->create($request->validated(), $request->user()?->id);

        return new CustomerResource($customer);
    }

    public function show(Customer $customer): CustomerResource
    {
        $this->authorize('view', $customer);

        return new CustomerResource($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        $customer = $this->customerService->update($customer, $request->validated(), $request->user()?->id);

        return new CustomerResource($customer);
    }

    public function destroy(Customer $customer): Response
    {
        $this->authorize('delete', $customer);
        $this->customerService->delete($customer);

        return response()->noContent();
    }
}
