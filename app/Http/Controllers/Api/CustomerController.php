<?php

namespace App\Http\Controllers\Api;

use App\Customer;
use App\Exports\CustomersTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Imports\CustomersBulkImport;
use App\Modules\TripManagement\Services\CustomerService;
use App\Support\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $customerService)
    {
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

    public function destroy(Request $request, Customer $customer): Response
    {
        $this->authorize('delete', $customer);

        $validated = $request->validate([
            'deleted_reason' => ['required', 'string', 'max:1000'],
        ]);

        $this->customerService->delete(
            $customer,
            $request->user()?->id,
            $validated['deleted_reason'],
        );

        return response()->noContent();
    }

  public function downloadBulkTemplate()
{
 \Log::info('Customer bulk template endpoint reached');

    $this->authorize('create', Customer::class);

    \Log::info('Customer bulk template authorization passed');

    return Excel::download(
        new CustomersTemplateExport(),
        'customers_template.xlsx'
    );
}

public function bulkUpload(Request $request): JsonResponse
{
    $this->authorize('create', Customer::class);

    $validated = $request->validate([
        'file' => [
            'required',
            'file',
            'mimes:xlsx,xls,csv',
            'max:10240',
        ],
    ]);

    $import = new CustomersBulkImport(
        tenantId: CurrentTenant::id(),
        userId: $request->user()?->id,
    );

    Excel::import($import, $validated['file']);

    return response()->json([
        'total' => $import->total,
        'successful' => $import->successful,
        'failed' => $import->failed,
        'errors' => $import->errors,
    ]);
}
}
