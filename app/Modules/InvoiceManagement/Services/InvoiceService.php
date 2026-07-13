<?php

namespace App\Modules\InvoiceManagement\Services;

use App\Branch;
use App\Customer;
use App\Enums\FreightBillingStatus;
use App\Enums\GstRegistrationType;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\TaxLiability;
use App\Enums\TripStatus;
use App\Invoice;
use App\InvoiceItem;
use App\InvoicePayment;
use App\Support\GstSettingsResolver;
use App\Trip;
use App\TripRouteLeg;
use App\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        private readonly GstSettingsResolver $gstSettingsResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->with(['customer:id,name,gst_number', 'trip:id,trip_number'])
            ->orderByDesc('invoice_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function find(string $id): Invoice
    {
        return Invoice::query()
            ->with([
                'customer.state',
                'trip:id,trip_number,status,start_at,end_at',
                'items.routeLeg',
                'payments.createdBy:id,name',
                'supplierState',
                'customerState',
                'branch:id,branch_name,company_id',
            ])
            ->findOrFail($id);
    }

    /**
     * @param  array{
     *     registration_type: ?GstRegistrationType,
     *     tax_liability: ?TaxLiability,
     *     state_id: ?int,
     *     state_code: ?string
     * }  $gstSettings
     * @return array{
     *     document_type: InvoiceDocumentType,
     *     gst_rate: float,
     *     supplier_state_id: ?int,
     *     supplier_state_code: ?string,
     *     customer_state_id: ?int,
     *     customer_state_code: ?string,
     *     place_of_supply: ?string,
     *     freight_amount: float,
     *     cgst_rate: float,
     *     cgst_amount: float,
     *     sgst_rate: float,
     *     sgst_amount: float,
     *     igst_rate: float,
     *     igst_amount: float,
     *     gst_amount: float,
     *     total_amount: float,
     *     displays_gst: bool,
     *     collects_gst: bool
     * }
     */
    public function calculateGst(
        array $gstSettings,
        float $freightAmount,
        float $gstRate,
        ?int $customerStateId,
        ?string $customerStateCode,
    ): array {
        $registrationType = $gstSettings['registration_type'] ?? null;
        $taxLiability = $gstSettings['tax_liability'] ?? null;

        $documentType = InvoiceDocumentType::fromGstSettings($registrationType, $taxLiability);
        $freightAmount = round(max(0, $freightAmount), 2);
        $gstRate = round(max(0, $gstRate), 3);

        $supplierStateId = $gstSettings['state_id'] ?? null;
        $supplierStateCode = $gstSettings['state_code'] ?? null;

        $cgstRate = 0.0;
        $sgstRate = 0.0;
        $igstRate = 0.0;
        $cgstAmount = 0.0;
        $sgstAmount = 0.0;
        $igstAmount = 0.0;
        $gstAmount = 0.0;

        if ($documentType->displaysGst() && $gstRate > 0) {
            $sameState = $supplierStateId !== null
                && $customerStateId !== null
                && (int) $supplierStateId === (int) $customerStateId;

            if ($sameState) {
                $halfRate = round($gstRate / 2, 3);
                $cgstRate = $halfRate;
                $sgstRate = $halfRate;
                $cgstAmount = round($freightAmount * ($halfRate / 100), 2);
                $sgstAmount = round($freightAmount * ($halfRate / 100), 2);
                $gstAmount = round($cgstAmount + $sgstAmount, 2);
            } else {
                $igstRate = $gstRate;
                $igstAmount = round($freightAmount * ($gstRate / 100), 2);
                $gstAmount = $igstAmount;
            }
        }

        $totalAmount = $documentType->collectsGst()
            ? round($freightAmount + $gstAmount, 2)
            : $freightAmount;

        return [
            'document_type' => $documentType,
            'gst_rate' => $gstRate,
            'supplier_state_id' => $supplierStateId,
            'supplier_state_code' => $supplierStateCode,
            'customer_state_id' => $customerStateId,
            'customer_state_code' => $customerStateCode,
            'place_of_supply' => $customerStateCode ?? $supplierStateCode,
            'freight_amount' => $freightAmount,
            'cgst_rate' => $cgstRate,
            'cgst_amount' => $cgstAmount,
            'sgst_rate' => $sgstRate,
            'sgst_amount' => $sgstAmount,
            'igst_rate' => $igstRate,
            'igst_amount' => $igstAmount,
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
            'displays_gst' => $documentType->displaysGst(),
            'collects_gst' => $documentType->collectsGst(),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<array<string, mixed>>
     */
    public function previewForTrip(Trip $trip, array $options = []): array
    {
        $this->assertTripIsInvoiceable($trip);
        $groups = $this->groupUnbilledLegsByCustomer($trip);
        $gstSettings = $this->resolveGstSettingsForTrip($trip);
        $defaultGstRate = (float) ($gstSettings['gst_rate'] ?? 0);
        $defaultInvoiceDate = $options['invoice_date'] ?? now()->toDateString();
        $dueDate = $options['due_date'] ?? null;

        if (
            $gstSettings['registration_type'] === GstRegistrationType::Regular
            && $gstSettings['gst_rate'] === null
        ) {
            throw ValidationException::withMessages([
                'gst_rate' => ['Set the company/branch GST rate before generating tax invoices.'],
            ]);
        }

        $previews = [];

        foreach ($groups as $customerId => $legs) {
            /** @var Collection<int, TripRouteLeg> $legs */
            $customer = $legs->first()?->customer
                ?? Customer::query()->with('state')->find($customerId);

            if ($customer === null) {
                throw ValidationException::withMessages([
                    'route_legs' => ['Each billable route must have a customer before invoicing.'],
                ]);
            }

            $customer->loadMissing('state');

            $freightAmount = round((float) $legs->sum(fn (TripRouteLeg $leg) => (float) $leg->freight_amount), 2);
            $groupInvoiceDate = $options['groups'][$customerId]['invoice_date'] ?? $defaultInvoiceDate;
            $groupDueDate = $options['groups'][$customerId]['due_date'] ?? $dueDate;

            $calc = $this->calculateGst(
                $gstSettings,
                $freightAmount,
                $defaultGstRate,
                $customer->state_id !== null ? (int) $customer->state_id : null,
                $customer->state?->code,
            );

            $previews[] = [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_gst_number' => $customer->gst_number,
                'route_leg_ids' => $legs->pluck('id')->values()->all(),
                'routes' => $legs->map(fn (TripRouteLeg $leg) => [
                    'id' => $leg->id,
                    'sequence_no' => $leg->sequence_no,
                    'from_location' => $leg->from_location,
                    'to_location' => $leg->to_location,
                    'goods_name' => $leg->goods_name,
                    'weight_ton' => (float) $leg->weight_ton,
                    'freight_amount' => (float) $leg->freight_amount,
                ])->values()->all(),
                'registration_type' => $gstSettings['registration_type']?->value,
                'tax_liability' => $gstSettings['tax_liability']?->value,
                'document_type' => $calc['document_type']->value,
                'document_type_label' => $calc['document_type']->label(),
                'gst_rate' => $defaultGstRate,
                'invoice_date' => $groupInvoiceDate,
                'due_date' => $groupDueDate,
                'displays_gst' => $calc['displays_gst'],
                'collects_gst' => $calc['collects_gst'],
                ...collect($calc)->except(['document_type'])->all(),
                'document_type_enum' => $calc['document_type'],
            ];
        }

        return $previews;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<Invoice>
     */
    public function generateForTrip(Trip $trip, array $options = [], ?User $user = null): array
    {
        return DB::transaction(function () use ($trip, $options): array {
            $previews = $this->previewForTrip($trip, $options);
            $invoices = [];

            foreach ($previews as $preview) {
                $invoice = Invoice::query()->create([
                    'tenant_id' => $trip->tenant_id,
                    'branch_id' => $trip->branch_id,
                    'trip_id' => $trip->id,
                    'customer_id' => $preview['customer_id'],
                    'invoice_number' => $this->generateInvoiceNumber(
                        $trip->tenant_id,
                        (int) $trip->branch_id,
                    ),
                    'registration_type' => $preview['registration_type'],
                    'tax_liability' => $preview['tax_liability'],
                    'document_type' => $preview['document_type'],
                    'gst_rate' => $preview['gst_rate'],
                    'supplier_state_id' => $preview['supplier_state_id'],
                    'customer_state_id' => $preview['customer_state_id'],
                    'freight_amount' => $preview['freight_amount'],
                    'cgst_rate' => $preview['cgst_rate'],
                    'cgst_amount' => $preview['cgst_amount'],
                    'sgst_rate' => $preview['sgst_rate'],
                    'sgst_amount' => $preview['sgst_amount'],
                    'igst_rate' => $preview['igst_rate'],
                    'igst_amount' => $preview['igst_amount'],
                    'gst_amount' => $preview['gst_amount'],
                    'total_amount' => $preview['total_amount'],
                    'invoice_date' => $preview['invoice_date'] ?? now()->toDateString(),
                    'due_date' => $preview['due_date'] ?? null,
                    'payment_status' => InvoicePaymentStatus::Pending,
                    'paid_amount' => 0,
                ]);

                $sequence = 1;
                foreach ($preview['routes'] as $route) {
                    $alreadyInvoiced = InvoiceItem::query()
                        ->where('trip_route_leg_id', $route['id'])
                        ->whereHas('invoice', function ($query): void {
                            $query->where('payment_status', '!=', InvoicePaymentStatus::Cancelled->value);
                        })
                        ->exists();

                    if ($alreadyInvoiced) {
                        throw ValidationException::withMessages([
                            'trip' => ['One or more routes are already included on an active invoice.'],
                        ]);
                    }

                    InvoiceItem::query()->create([
                        'tenant_id' => $trip->tenant_id,
                        'branch_id' => $trip->branch_id,
                        'invoice_id' => $invoice->id,
                        'trip_route_leg_id' => $route['id'],
                        'sequence_no' => $sequence++,
                        'from_location' => $route['from_location'],
                        'to_location' => $route['to_location'],
                        'goods_name' => $route['goods_name'],
                        'weight_ton' => $route['weight_ton'],
                        'freight_amount' => $route['freight_amount'],
                    ]);

                    TripRouteLeg::query()
                        ->withoutGlobalScopes()
                        ->whereKey($route['id'])
                        ->update(['billing_status' => FreightBillingStatus::Invoiced->value]);
                }

                $invoices[] = $invoice->fresh(['customer', 'items', 'trip']);
            }

            return $invoices;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordPayment(Invoice $invoice, array $data, ?User $user = null): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $data, $user): InvoicePayment {
            if ($invoice->isCancelled()) {
                throw ValidationException::withMessages([
                    'invoice' => ['Cannot record a payment on a cancelled invoice.'],
                ]);
            }

            $amount = round((float) $data['amount'], 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Payment amount must be greater than zero.'],
                ]);
            }

            $remaining = $invoice->remainingAmount();
            if ($amount - $remaining > 0.01) {
                throw ValidationException::withMessages([
                    'amount' => ["Payment amount cannot exceed the remaining balance of {$remaining}."],
                ]);
            }

            $payment = InvoicePayment::query()->create([
                'tenant_id' => $invoice->tenant_id,
                'branch_id' => $invoice->branch_id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'payment_method' => $data['payment_method'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            $invoice->refreshPaymentStatus();

            return $payment;
        });
    }

    public function deletePayment(Invoice $invoice, InvoicePayment $payment): void
    {
        throw ValidationException::withMessages([
            'payment' => ['Invoice payments cannot be deleted after they are recorded.'],
        ]);
    }

    public function cancelInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            if ($invoice->isCancelled()) {
                throw ValidationException::withMessages([
                    'invoice' => ['This invoice is already cancelled.'],
                ]);
            }

            if ((float) $invoice->paid_amount > 0) {
                throw ValidationException::withMessages([
                    'invoice' => ['Cannot cancel an invoice that has payments.'],
                ]);
            }

            $legIds = $invoice->items()->pluck('trip_route_leg_id');

            TripRouteLeg::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $legIds)
                ->update(['billing_status' => FreightBillingStatus::Unbilled->value]);

            $invoice->forceFill([
                'payment_status' => InvoicePaymentStatus::Cancelled,
            ])->save();
        });
    }

    public function pdf(Invoice $invoice)
    {
        $invoice->loadMissing([
            'customer.state',
            'customer.city',
            'customerState',
            'supplierState',
            'items',
            'trip.vehicle',
            'branch.company.state',
            'tenant.state',
        ]);

        $company = $invoice->branch?->company ?? $invoice->tenant;

        return Pdf::loadView('invoices.pdf.party-bill-pdf-modern', [
            'invoice' => $invoice,
            'company' => $company,
        ])->setPaper('a4');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = Invoice::query();

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (! empty($filters['trip_id'])) {
            $query->where('trip_id', $filters['trip_id']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['document_type'])) {
            $query->where('document_type', $filters['document_type']);
        }

        if (! empty($filters['due_filter'])) {
            match ($filters['due_filter']) {
                'overdue' => $query
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', now()->toDateString())
                    ->whereNotIn('payment_status', [
                        InvoicePaymentStatus::Paid->value,
                        InvoicePaymentStatus::Cancelled->value,
                    ]),
                'due_soon' => $query
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '>=', now()->toDateString())
                    ->whereDate('due_date', '<=', now()->addDays(7)->toDateString())
                    ->whereNotIn('payment_status', [
                        InvoicePaymentStatus::Paid->value,
                        InvoicePaymentStatus::Cancelled->value,
                    ]),
                default => null,
            };
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('trip', fn (Builder $trip) => $trip->where('trip_number', 'like', "%{$search}%"));
            });
        }

        return $query;
    }

    private function generateInvoiceNumber(string $tenantId, int $branchId): string
    {
        $year = (int) now()->format('Y');
        $branchCode = sprintf('BR%03d', $branchId);

        return DB::transaction(function () use ($tenantId, $branchId, $year, $branchCode): string {
            $prefix = sprintf('INV-%s-%d-', $branchCode, $year);

            $lastNumber = Invoice::query()
                ->withoutGlobalScopes()
                ->withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('branch_id', $branchId)
                ->where('invoice_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('invoice_number')
                ->value('invoice_number');

            $sequence = 1;
            if ($lastNumber !== null) {
                $suffix = substr((string) $lastNumber, strlen($prefix));
                $sequence = (is_numeric($suffix) ? (int) $suffix : 0) + 1;
            }

            $padded = str_pad((string) $sequence, max(4, strlen((string) $sequence)), '0', STR_PAD_LEFT);

            return sprintf('INV-%s-%d-%s', $branchCode, $year, $padded);
        });
    }

    /**
     * @return Collection<string, Collection<int, TripRouteLeg>>
     */
    private function groupUnbilledLegsByCustomer(Trip $trip): Collection
    {
        $trip->loadMissing(['routeLegs.customer.state']);

        $legs = $trip->routeLegs
            ->filter(fn (TripRouteLeg $leg) => $leg->isRevenueLeg())
            ->filter(function (TripRouteLeg $leg): bool {
                $status = $leg->billing_status instanceof FreightBillingStatus
                    ? $leg->billing_status
                    : FreightBillingStatus::tryFrom((string) ($leg->billing_status ?? 'unbilled'));

                return $status === null || $status === FreightBillingStatus::Unbilled || $status === FreightBillingStatus::Billed;
            })
            ->filter(fn (TripRouteLeg $leg) => (float) $leg->freight_amount > 0);

        if ($legs->isEmpty()) {
            throw ValidationException::withMessages([
                'trip' => ['There are no unbilled revenue routes available to invoice on this trip.'],
            ]);
        }

        $missingCustomer = $legs->first(fn (TripRouteLeg $leg) => blank($leg->customer_id));
        if ($missingCustomer !== null) {
            throw ValidationException::withMessages([
                'route_legs' => ['All billable routes must have a customer before generating invoices.'],
            ]);
        }

        return $legs->groupBy(fn (TripRouteLeg $leg) => (string) $leg->customer_id);
    }

    private function assertTripIsInvoiceable(Trip $trip): void
    {
        $status = $trip->status instanceof TripStatus
            ? $trip->status
            : TripStatus::tryFrom((string) $trip->status);

        if ($status !== TripStatus::Completed) {
            throw ValidationException::withMessages([
                'trip' => ['Invoices can only be generated for completed trips.'],
            ]);
        }
    }

    /**
     * @return array{
     *     inherit_company_gst: bool,
     *     registration_type: ?GstRegistrationType,
     *     tax_liability: ?TaxLiability,
     *     gst_number: ?string,
     *     gst_rate: ?float,
     *     state_id: ?int,
     *     state_code: ?string,
     *     source: 'company'|'branch'
     * }
     */
    private function resolveGstSettingsForTrip(Trip $trip): array
    {
        $branch = Branch::query()
            ->with(['company.state', 'state'])
            ->find($trip->branch_id);

        if ($branch === null) {
            throw ValidationException::withMessages([
                'branch' => ['Branch context is required to generate invoices.'],
            ]);
        }

        $settings = $this->gstSettingsResolver->forBranch($branch);

        if ($settings['registration_type']?->showsGstFields() && $settings['state_id'] === null) {
            throw ValidationException::withMessages([
                'state_id' => ['Set the company/branch GST state before generating tax invoices.'],
            ]);
        }

        return $settings;
    }
}
