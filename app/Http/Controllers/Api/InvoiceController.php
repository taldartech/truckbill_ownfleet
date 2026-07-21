<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoicePaymentRequest;
use App\Http\Requests\TripInvoicesRequest;
use App\Http\Resources\InvoicePaymentResource;
use App\Http\Resources\InvoiceResource;
use App\Invoice;
use App\InvoicePayment;
use App\Modules\InvoiceManagement\Services\InvoiceService;
use App\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = $this->invoiceService->paginate(
            perPage: (int) $request->integer('per_page', 25),
            filters: $request->only([
                'search',
                'customer_id',
                'trip_id',
                'payment_status',
                'document_type',
                'due_filter',
            ]),
        );

        return InvoiceResource::collection($invoices);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource($this->invoiceService->find($invoice->id));
    }

    public function previewForTrip(TripInvoicesRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('create', Invoice::class);
        $this->authorize('view', $trip);

        $previews = $this->invoiceService->previewForTrip($trip, $request->validated());

        return response()->json([
            'data' => array_map(static function (array $preview): array {
                unset($preview['document_type_enum']);

                return $preview;
            }, $previews),
        ]);
    }

    public function generateForTrip(TripInvoicesRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('create', Invoice::class);
        $this->authorize('view', $trip);

        $invoices = $this->invoiceService->generateForTrip(
            $trip,
            $request->validated(),
            $request->user(),
        );

        return InvoiceResource::collection(collect($invoices))
            ->response()
            ->setStatusCode(201);
    }

    public function pdf(Invoice $invoice): SymfonyResponse
    {
        $this->authorize('export', $invoice);

        $filename = str_replace(['/', '\\'], '-', $invoice->invoice_number).'.pdf';

        return $this->invoiceService->pdf($invoice)->stream($filename);
    }

    public function storePayment(StoreInvoicePaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);

        $payment = $this->invoiceService->recordPayment(
            $invoice,
            $request->validated(),
            $request->user(),
        );

        return (new InvoicePaymentResource($payment->load('createdBy')))
            ->response()
            ->setStatusCode(201);
    }

    public function destroyPayment(Invoice $invoice, InvoicePayment $payment): Response
    {
        $this->authorize('update', $invoice);

        $this->invoiceService->deletePayment($invoice, $payment);

        return response()->noContent();
    }

    public function destroy(Invoice $invoice, Request $request): Response
    {
        $this->authorize('delete', $invoice);

        $this->invoiceService->cancelInvoice($invoice, $request->user());

        return response()->noContent();
    }
}
