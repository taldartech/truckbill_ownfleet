<?php

namespace App\Modules\InvoiceManagement\Services;

use App\Branch;
use App\Customer;
use App\Enums\FreightBillingStatus;
use App\Enums\GstRegistrationType;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoiceParticularsMode;
use App\Enums\InvoicePaymentStatus;
use App\Enums\TaxLiability;
use App\Enums\TripStatus;
use App\Invoice;
use App\InvoiceAuditLog;
use App\InvoiceItem;
use App\InvoicePayment;
use App\Support\GstSettingsResolver;
use App\Support\TripNumberGenerator;
use App\Trip;
use App\TripRouteLeg;
use App\TripStatusHistory;
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
        private readonly TripNumberGenerator $tripNumberGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $relations = ['customer:id,name,gst_number', 'trip:id,trip_number'];

        if (($filters['archived'] ?? null) === 'archived') {
            $relations[] = 'deletedBy:id,name';
        }

        $query = $this->filteredQuery($filters)->with($relations);

        if (($filters['archived'] ?? null) === 'archived') {
            $query->orderByDesc('deleted_at')->orderByDesc('id');
        } else {
            $query->orderByDesc('invoice_date')->orderByDesc('created_at');
        }

        return $query->paginate($perPage);
    }

    public function find(string $id): Invoice
    {
        return Invoice::query()
            ->withTrashed()
            ->with([
                'customer.state',
                'trip:id,trip_number,status,start_at,end_at',
                'items.routeLeg',
                'payments.createdBy:id,name',
                'auditLogs.performer:id,name',
                'supplierState',
                'customerState',
                'branch:id,branch_name,company_id',
                'deletedBy:id,name',
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
        bool $isBroker = false,
    ): array {
        $freightAmount = round(max(0, $freightAmount), 2);
        $supplierStateId = $gstSettings['state_id'] ?? null;
        $supplierStateCode = $gstSettings['state_code'] ?? null;

        if ($isBroker) {
            return [
                'document_type' => InvoiceDocumentType::BillInvoice,
                'gst_rate' => 0.0,
                'supplier_state_id' => $supplierStateId,
                'supplier_state_code' => $supplierStateCode,
                'customer_state_id' => $customerStateId,
                'customer_state_code' => $customerStateCode,
                'place_of_supply' => $customerStateCode ?? $supplierStateCode,
                'freight_amount' => $freightAmount,
                'cgst_rate' => 0.0,
                'cgst_amount' => 0.0,
                'sgst_rate' => 0.0,
                'sgst_amount' => 0.0,
                'igst_rate' => 0.0,
                'igst_amount' => 0.0,
                'gst_amount' => 0.0,
                'total_amount' => $freightAmount,
                'displays_gst' => false,
                'collects_gst' => false,
            ];
        }

        $registrationType = $gstSettings['registration_type'] ?? null;
        $taxLiability = $gstSettings['tax_liability'] ?? null;

        $documentType = InvoiceDocumentType::fromGstSettings($registrationType, $taxLiability);
        $gstRate = round(max(0, $gstRate), 3);

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
        $trip->loadMissing(['vehicle', 'driver']);
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
        $reservedSequencesByDate = [];

        $tripSnapshot = [
            'trip_id' => $trip->id,
            'trip_number' => $trip->trip_number,
            'trip_type' => $trip->trip_type?->value ?? $trip->trip_type,
            'status' => $trip->status?->value ?? $trip->status,
            'start_at' => $trip->start_at?->toDateTimeString(),
            'end_at' => $trip->end_at?->toDateTimeString(),
            'vehicle_registration_number' => $trip->vehicle?->registration_number,
            'driver_name' => $trip->driver?->name,
        ];

        foreach ($groups as $customerId => $legs) {
            /** @var Collection<int, TripRouteLeg> $legs */
            $customer = $legs->first()?->customer
                ?? Customer::query()->with('state')->find($customerId);

            if ($customer === null) {
                throw ValidationException::withMessages([
                    'route_legs' => ['Each billable route must have a customer before invoicing.'],
                ]);
            }

            $customer->loadMissing(['state', 'city']);

            $customerSnapshot = [
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'gst_number' => $customer->gst_number,
                'pan_number' => $customer->pan_number,
                'address' => $customer->address,
                'city_name' => $customer->city?->name,
                'state_code' => $customer->state?->code,
                'state_name' => $customer->state?->name,
            ];

            $routeFreightAmount = round((float) $legs->sum(fn (TripRouteLeg $leg) => (float) $leg->freight_amount), 2);
            $customItems = $this->normalizeCustomItems($options['groups'][$customerId]['custom_items'] ?? []);
            $customAmount = round(
                (float) collect($customItems)->sum(fn (array $item) => (float) $item['amount']),
                2,
            );
            $freightAmount = round($routeFreightAmount + $customAmount, 2);
            $groupInvoiceDate = $options['groups'][$customerId]['invoice_date'] ?? $defaultInvoiceDate;
            $groupDueDate = $options['groups'][$customerId]['due_date'] ?? $dueDate;
            $hasExplicitSequence = array_key_exists('invoice_sequence', $options['groups'][$customerId] ?? [])
                && ($options['groups'][$customerId]['invoice_sequence'] ?? null) !== null
                && ($options['groups'][$customerId]['invoice_sequence'] ?? null) !== '';
            $groupSequence = $hasExplicitSequence
                ? (int) $options['groups'][$customerId]['invoice_sequence']
                : null;

            $calc = $this->calculateGst(
                $gstSettings,
                $freightAmount,
                $defaultGstRate,
                $customer->state_id !== null ? (int) $customer->state_id : null,
                $customer->state?->code,
                (bool) $customer->is_broker,
            );

            $dateKey = (string) $groupInvoiceDate;
            $reservedSequencesByDate[$dateKey] ??= [];

            if ($groupSequence === null) {
                $invoiceNumberPreview = $this->tripNumberGenerator->preview(
                    TripNumberGenerator::DOCUMENT_INVOICE,
                    (string) $trip->tenant_id,
                    $trip->branch_id !== null ? (int) $trip->branch_id : null,
                    $groupInvoiceDate,
                    null,
                );
                $groupSequence = (int) $invoiceNumberPreview['sequence'];

                while (in_array($groupSequence, $reservedSequencesByDate[$dateKey], true)) {
                    $groupSequence++;
                }

                $invoiceNumberPreview = $this->tripNumberGenerator->preview(
                    TripNumberGenerator::DOCUMENT_INVOICE,
                    (string) $trip->tenant_id,
                    $trip->branch_id !== null ? (int) $trip->branch_id : null,
                    $groupInvoiceDate,
                    $groupSequence,
                );
            } else {
                $invoiceNumberPreview = $this->tripNumberGenerator->preview(
                    TripNumberGenerator::DOCUMENT_INVOICE,
                    (string) $trip->tenant_id,
                    $trip->branch_id !== null ? (int) $trip->branch_id : null,
                    $groupInvoiceDate,
                    $groupSequence,
                );
            }

            $reservedSequencesByDate[$dateKey][] = (int) $invoiceNumberPreview['sequence'];

            $routes = $legs->map(fn (TripRouteLeg $leg) => [
                'id' => $leg->id,
                'sequence_no' => $leg->sequence_no,
                'from_location' => $leg->from_city,
                'to_location' => $leg->to_city,
                'from_address' => $leg->from_address,
                'to_address' => $leg->to_address,
                'goods_name' => $leg->goods_name,
                'weight_ton' => (float) $leg->weight_ton,
                'freight_amount' => (float) $leg->freight_amount,
                'consignor' => $leg->consignor,
                'consignee' => $leg->consignee,
            ])->values()->all();

            $particularsMode = $this->resolveParticularsMode($options, $customerId);

            $previews[] = [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_gst_number' => $customer->gst_number,
                'customer_snapshot' => $customerSnapshot,
                'is_broker' => (bool) $customer->is_broker,
                'route_leg_ids' => $legs->pluck('id')->values()->all(),
                'particulars_mode' => $particularsMode->value,
                'routes' => $routes,
                'invoice_lines' => $this->buildInvoiceLines($routes, $particularsMode),
                'trip_snapshot' => $tripSnapshot,
                'custom_items' => $customItems,
                'route_freight_amount' => $routeFreightAmount,
                'custom_amount' => $customAmount,
                'registration_type' => $gstSettings['registration_type']?->value,
                'tax_liability' => $gstSettings['tax_liability']?->value,
                'document_type' => $calc['document_type']->value,
                'document_type_label' => $calc['document_type']->label(),
                'gst_rate' => $defaultGstRate,
                'invoice_date' => $groupInvoiceDate,
                'due_date' => $groupDueDate,
                'invoice_number' => $invoiceNumberPreview['number'],
                'invoice_sequence' => $invoiceNumberPreview['sequence'],
                'invoice_sequence_explicit' => $hasExplicitSequence,
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
        return DB::transaction(function () use ($trip, $options, $user): array {
            $previews = $this->previewForTrip($trip, $options);
            $this->assertDistinctInvoiceSequences($previews);
            $invoices = [];
            $customAmountCreated = 0.0;

            foreach ($previews as $preview) {
                if ($trip->branch_id === null) {
                    throw ValidationException::withMessages([
                        'branch_id' => ['Trip branch is required to generate invoice numbers.'],
                    ]);
                }

                $allocated = $this->tripNumberGenerator->allocate(
                    TripNumberGenerator::DOCUMENT_INVOICE,
                    (string) $trip->tenant_id,
                    (int) $trip->branch_id,
                    $preview['invoice_date'] ?? now()->toDateString(),
                    ! empty($preview['invoice_sequence_explicit'])
                        ? (int) $preview['invoice_sequence']
                        : null,
                );

                $invoice = Invoice::query()->create([
                    'tenant_id' => $trip->tenant_id,
                    'branch_id' => $trip->branch_id,
                    'trip_id' => $trip->id,
                    'customer_id' => $preview['customer_id'],
                    'customer_snapshot' => $preview['customer_snapshot'] ?? null,
                    'trip_snapshot' => $preview['trip_snapshot'] ?? null,
                    'invoice_number' => $allocated['number'],
                    'registration_type' => $preview['registration_type'],
                    'tax_liability' => $preview['tax_liability'],
                    'document_type' => $preview['document_type'],
                    'particulars_mode' => $preview['particulars_mode'] ?? InvoiceParticularsMode::Separate->value,
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
                $invoiceLines = $preview['invoice_lines'] ?? $this->buildInvoiceLines(
                    $preview['routes'],
                    InvoiceParticularsMode::tryFrom((string) ($preview['particulars_mode'] ?? ''))
                        ?? InvoiceParticularsMode::Separate,
                );

                foreach ($invoiceLines as $line) {
                    $legIds = $line['route_leg_ids'] ?? [$line['id']];

                    foreach ($legIds as $legId) {
                        $alreadyInvoiced = InvoiceItem::query()
                            ->where('trip_route_leg_id', $legId)
                            ->whereHas('invoice', function ($query): void {
                                $query->where('payment_status', '!=', InvoicePaymentStatus::Cancelled->value);
                            })
                            ->exists();

                        if ($alreadyInvoiced) {
                            throw ValidationException::withMessages([
                                'trip' => ['One or more routes are already included on an active invoice.'],
                            ]);
                        }
                    }

                    InvoiceItem::query()->create([
                        'tenant_id' => $trip->tenant_id,
                        'branch_id' => $trip->branch_id,
                        'invoice_id' => $invoice->id,
                        'trip_route_leg_id' => $line['id'],
                        'sequence_no' => $sequence++,
                        'from_location' => $line['from_location'],
                        'to_location' => $line['to_location'],
                        'consignor' => $line['consignor'] ?? null,
                        'consignee' => $line['consignee'] ?? null,
                        'from_address' => $line['from_address'] ?? null,
                        'to_address' => $line['to_address'] ?? null,
                        'goods_name' => $line['goods_name'],
                        'weight_ton' => $line['weight_ton'],
                        'freight_amount' => $line['freight_amount'],
                        'route_legs_snapshot' => $line['route_legs_snapshot'] ?? null,
                    ]);

                    foreach ($legIds as $legId) {
                        TripRouteLeg::query()
                            ->withoutGlobalScopes()
                            ->whereKey($legId)
                            ->update(['billing_status' => FreightBillingStatus::Invoiced->value]);
                    }
                }

                foreach ($preview['custom_items'] ?? [] as $customItem) {
                    InvoiceItem::query()->create([
                        'tenant_id' => $trip->tenant_id,
                        'branch_id' => $trip->branch_id,
                        'invoice_id' => $invoice->id,
                        'trip_route_leg_id' => null,
                        'sequence_no' => $sequence++,
                        'from_location' => null,
                        'to_location' => null,
                        'goods_name' => $customItem['description'],
                        'weight_ton' => 0,
                        'freight_amount' => $customItem['amount'],
                    ]);

                    $customAmountCreated += (float) $customItem['amount'];
                }

                $invoices[] = $invoice->fresh(['customer', 'items', 'trip']);
            }

            if ($customAmountCreated > 0) {
                $trip->forceFill([
                    'total_custom_amount' => round(
                        (float) $trip->total_custom_amount + $customAmountCreated,
                        2,
                    ),
                ])->save();
            }

            $this->markTripInvoiced($trip, $user);

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

            $amount = round((float) ($data['amount'] ?? 0), 2);
            $tdsAmount = round((float) ($data['tds_amount'] ?? 0), 2);
            $deductionAmount = round((float) ($data['deduction_amount'] ?? 0), 2);
            $totalSettled = round($amount + $tdsAmount + $deductionAmount, 2);

            if ($amount < 0 || $tdsAmount < 0 || $deductionAmount < 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Payment and deduction amounts cannot be negative.'],
                ]);
            }

            if ($totalSettled <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Total payment settlement (Amount + TDS + Deductions) must be greater than zero.'],
                ]);
            }

            if ($deductionAmount > 0 && empty(trim((string) ($data['deduction_reason'] ?? '')))) {
                throw ValidationException::withMessages([
                    'deduction_reason' => ['Please provide a reason for the deduction / discount (e.g. Shortage, Discount, Damage).'],
                ]);
            }

            $priorTds = $invoice->totalTdsDeducted();
            $maxAllowableTds = (float) ($invoice->freight_amount > 0 ? $invoice->freight_amount : $invoice->total_amount);
            if ($tdsAmount > 0 && ($priorTds + $tdsAmount) > ($maxAllowableTds + 0.01)) {
                throw ValidationException::withMessages([
                    'tds_amount' => ['Total TDS across payments (₹'.number_format($priorTds + $tdsAmount, 2).') cannot exceed the invoice freight amount of ₹'.number_format($maxAllowableTds, 2).'.'],
                ]);
            }

            $remaining = $invoice->remainingAmount();
            if ($totalSettled - $remaining > 0.01) {
                throw ValidationException::withMessages([
                    'amount' => ['Total settlement amount (₹'.number_format($totalSettled, 2).') cannot exceed the remaining balance of ₹'.number_format($remaining, 2).'.'],
                ]);
            }

            $payment = InvoicePayment::query()->create([
                'tenant_id' => $invoice->tenant_id,
                'branch_id' => $invoice->branch_id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'tds_amount' => $tdsAmount,
                'deduction_amount' => $deductionAmount,
                'deduction_reason' => $data['deduction_reason'] ?? null,
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

    /**
     * @param  array{custom_items: list<array{description: string, amount: float|int|string}>}  $data
     */
    public function updatePendingInvoice(Invoice $invoice, array $data, ?User $user = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $data, $user): Invoice {
            $invoice->refresh()->loadMissing(['items', 'customer.state']);

            $this->assertInvoiceChargesEditable($invoice);

            $routeItems = $invoice->items->filter(
                fn (InvoiceItem $item): bool => $item->trip_route_leg_id !== null,
            );

            $previousCustomItems = $invoice->items
                ->filter(fn (InvoiceItem $item): bool => $item->trip_route_leg_id === null)
                ->map(fn (InvoiceItem $item): array => [
                    'description' => (string) $item->goods_name,
                    'amount' => (float) $item->freight_amount,
                ])
                ->values()
                ->all();

            $routeFreightAmount = round(
                (float) $routeItems->sum(fn (InvoiceItem $item): float => (float) $item->freight_amount),
                2,
            );
            $customItems = $this->normalizeCustomItems($data['custom_items'] ?? []);
            $customAmount = round(
                (float) collect($customItems)->sum(fn (array $item): float => (float) $item['amount']),
                2,
            );
            $freightAmount = round($routeFreightAmount + $customAmount, 2);

            $customer = $invoice->customer;
            $gstSettings = $this->resolveGstSettingsForInvoice($invoice);

            $calc = $this->calculateGst(
                $gstSettings,
                $freightAmount,
                (float) $invoice->gst_rate,
                $invoice->customer_state_id !== null
                    ? (int) $invoice->customer_state_id
                    : ($customer?->state_id !== null ? (int) $customer->state_id : null),
                $customer?->state?->code,
                (bool) ($customer?->is_broker ?? false),
            );

            InvoiceItem::query()
                ->where('invoice_id', $invoice->id)
                ->whereNull('trip_route_leg_id')
                ->delete();

            $sequence = (int) ($routeItems->max('sequence_no') ?? $routeItems->count());

            foreach ($customItems as $customItem) {
                $sequence++;

                InvoiceItem::query()->create([
                    'tenant_id' => $invoice->tenant_id,
                    'branch_id' => $invoice->branch_id,
                    'invoice_id' => $invoice->id,
                    'trip_route_leg_id' => null,
                    'sequence_no' => $sequence,
                    'from_location' => null,
                    'to_location' => null,
                    'goods_name' => $customItem['description'],
                    'weight_ton' => 0,
                    'freight_amount' => $customItem['amount'],
                ]);
            }

            $previousTotals = [
                'freight_amount' => (float) $invoice->freight_amount,
                'custom_amount' => round((float) collect($previousCustomItems)->sum('amount'), 2),
                'gst_amount' => (float) $invoice->gst_amount,
                'total_amount' => (float) $invoice->total_amount,
                'custom_items' => $previousCustomItems,
            ];

            $invoice->forceFill([
                'freight_amount' => $calc['freight_amount'],
                'cgst_rate' => $calc['cgst_rate'],
                'cgst_amount' => $calc['cgst_amount'],
                'sgst_rate' => $calc['sgst_rate'],
                'sgst_amount' => $calc['sgst_amount'],
                'igst_rate' => $calc['igst_rate'],
                'igst_amount' => $calc['igst_amount'],
                'gst_amount' => $calc['gst_amount'],
                'total_amount' => $calc['total_amount'],
            ])->save();

            $customDelta = round($customAmount - $previousTotals['custom_amount'], 2);

            if ($invoice->trip_id !== null && abs($customDelta) >= 0.01) {
                $trip = Trip::query()->withoutGlobalScopes()->find($invoice->trip_id);

                if ($trip !== null) {
                    $trip->forceFill([
                        'total_custom_amount' => round(
                            max(0, (float) $trip->total_custom_amount + $customDelta),
                            2,
                        ),
                    ])->save();
                }
            }

            InvoiceAuditLog::query()->create([
                'tenant_id' => $invoice->tenant_id,
                'branch_id' => $invoice->branch_id,
                'invoice_id' => $invoice->id,
                'event_type' => 'charges_updated',
                'payload' => [
                    'route_freight_amount' => $routeFreightAmount,
                    'previous' => $previousTotals,
                    'current' => [
                        'freight_amount' => $calc['freight_amount'],
                        'custom_amount' => $customAmount,
                        'gst_amount' => $calc['gst_amount'],
                        'total_amount' => $calc['total_amount'],
                        'custom_items' => $customItems,
                    ],
                ],
                'performed_by' => $user?->id,
                'performed_at' => now(),
            ]);

            return $invoice->fresh(['customer.state', 'items', 'trip', 'payments', 'auditLogs.performer']);
        });
    }

    public function cancelInvoice(Invoice $invoice, ?User $user = null): void
    {
        DB::transaction(function () use ($invoice, $user): void {
            if ($invoice->isCancelled()) {
                throw ValidationException::withMessages([
                    'invoice' => ['This invoice is already cancelled.'],
                ]);
            }

            if ($invoice->hasRecordedPayments()) {
                throw ValidationException::withMessages([
                    'invoice' => ['Cannot cancel an invoice that has payments.'],
                ]);
            }

            $this->releaseInvoiceBilling($invoice);

            $invoice->forceFill([
                'payment_status' => InvoicePaymentStatus::Cancelled,
            ])->save();

            if ($invoice->trip_id !== null) {
                $trip = Trip::query()->withoutGlobalScopes()->find($invoice->trip_id);

                if ($trip !== null) {
                    $this->revertTripFromInvoicedIfNeeded($trip, $user, 'Invoice cancelled');
                }
            }
        });
    }

    public function deleteInvoice(Invoice $invoice, string $deletionReason, ?User $user = null): void
    {
        DB::transaction(function () use ($invoice, $deletionReason, $user): void {
            $invoice->refresh();

            if ($invoice->trashed()) {
                throw ValidationException::withMessages([
                    'invoice' => ['This invoice is already deleted.'],
                ]);
            }

            if ($invoice->hasRecordedPayments()) {
                throw ValidationException::withMessages([
                    'invoice' => ['Cannot delete an invoice that has payments.'],
                ]);
            }

            if (! $invoice->isLatestForBranch()) {
                throw ValidationException::withMessages([
                    'invoice' => ['Only the latest invoice can be deleted. Delete newer invoices first.'],
                ]);
            }

            $tripId = $invoice->trip_id;

            if (! $invoice->isCancelled()) {
                $this->releaseInvoiceBilling($invoice);
            }

            $invoice->forceFill([
                'invoice_number' => $invoice->invoice_number.'__deleted_'.$invoice->id,
                'deleted_by' => $user?->id,
                'deletion_reason' => trim($deletionReason),
            ])->save();

            $invoice->delete();

            if ($tripId !== null) {
                $trip = Trip::query()->withoutGlobalScopes()->find($tripId);

                if ($trip !== null) {
                    $this->revertTripFromInvoicedIfNeeded($trip, $user, 'Invoice deleted');
                }
            }
        });
    }

    private function releaseInvoiceBilling(Invoice $invoice): void
    {
        $invoice->loadMissing('items');

        $particularsMode = $invoice->particulars_mode instanceof InvoiceParticularsMode
            ? $invoice->particulars_mode
            : InvoiceParticularsMode::tryFrom((string) ($invoice->particulars_mode ?? ''))
                ?? InvoiceParticularsMode::Separate;

        if (
            $particularsMode === InvoiceParticularsMode::Combined
            && $invoice->trip_id !== null
            && $invoice->customer_id !== null
        ) {
            TripRouteLeg::query()
                ->withoutGlobalScopes()
                ->where('trip_id', $invoice->trip_id)
                ->where('customer_id', $invoice->customer_id)
                ->where('billing_status', FreightBillingStatus::Invoiced->value)
                ->update(['billing_status' => FreightBillingStatus::Unbilled->value]);
        } else {
            $legIds = $invoice->items
                ->pluck('trip_route_leg_id')
                ->filter()
                ->values()
                ->all();

            if ($legIds !== []) {
                TripRouteLeg::query()
                    ->withoutGlobalScopes()
                    ->whereIn('id', $legIds)
                    ->update(['billing_status' => FreightBillingStatus::Unbilled->value]);
            }
        }

        $customAmount = round(
            (float) $invoice->items
                ->filter(fn (InvoiceItem $item): bool => $item->trip_route_leg_id === null)
                ->sum(fn (InvoiceItem $item): float => (float) $item->freight_amount),
            2,
        );

        if ($invoice->trip_id === null || $customAmount <= 0) {
            return;
        }

        $trip = Trip::query()->withoutGlobalScopes()->find($invoice->trip_id);

        if ($trip === null) {
            return;
        }

        $trip->forceFill([
            'total_custom_amount' => round(
                max(0, (float) $trip->total_custom_amount - $customAmount),
                2,
            ),
        ])->save();
    }

    public function pdf(Invoice $invoice)
    {
        $invoice->loadMissing([
            'payments',
            'customer.state',
            'customer.city',
            'customerState',
            'supplierState',
            'items.routeLeg.fromStation.city',
            'items.routeLeg.fromStation.state',
            'items.routeLeg.toStation.city',
            'items.routeLeg.toStation.state',
            'trip.routeLegs.fromStation.city',
            'trip.routeLegs.fromStation.state',
            'trip.routeLegs.toStation.city',
            'trip.routeLegs.toStation.state',
            'trip.vehicle',
            'trip.driver',
            'branch.company.state',
            'tenant.state',
        ]);

        if (! $invoice->relationLoaded('payments') || ($invoice->payments->isEmpty() && (float) $invoice->paid_amount > 0)) {
            $invoice->setRelation(
                'payments',
                $invoice->payments()->withoutGlobalScopes()->where('invoice_id', $invoice->id)->orderByDesc('paid_at')->get()
            );
        }

        $company = $invoice->branch?->company ?? $invoice->tenant;

        return Pdf::loadView('invoices.pdf.party-bill-pdf-modern', [
            'invoice' => $invoice,
            'company' => $company,
            'document_color' => $company?->documentColor(),
            'document_text_color' => $company?->documentTextColor() ?? '#000000',
            'document_color_soft' => $company?->documentColorWithOpacity(0.2),
            'signature_src' => $company?->signatureDataUriForPdf(),
        ])->setPaper('a4');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = Invoice::query();

        if (($filters['archived'] ?? null) === 'archived') {
            $query->onlyTrashed();
        }

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

    /**
     * @param  list<array<string, mixed>>  $previews
     */
    private function assertDistinctInvoiceSequences(array $previews): void
    {
        $seen = [];

        foreach ($previews as $preview) {
            $sequence = isset($preview['invoice_sequence']) ? (int) $preview['invoice_sequence'] : null;
            $invoiceDate = (string) ($preview['invoice_date'] ?? '');

            if ($sequence === null) {
                continue;
            }

            $key = $invoiceDate.'|'.$sequence;

            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    "groups.{$preview['customer_id']}.invoice_sequence" => [
                        'Each invoice group must use a distinct sequence for the same invoice date.',
                    ],
                ]);
            }

            $seen[$key] = true;
        }
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

        if (! in_array($status, [TripStatus::Completed, TripStatus::Invoiced], true)) {
            throw ValidationException::withMessages([
                'trip' => ['Invoices can only be generated for completed trips.'],
            ]);
        }
    }

    private function markTripInvoiced(Trip $trip, ?User $user = null): void
    {
        $status = $trip->status instanceof TripStatus
            ? $trip->status
            : TripStatus::tryFrom((string) $trip->status);

        if ($status === TripStatus::Invoiced) {
            return;
        }

        if ($status !== TripStatus::Completed) {
            throw ValidationException::withMessages([
                'trip' => ['Invoices can only be generated for completed trips.'],
            ]);
        }

        $trip->update(['status' => TripStatus::Invoiced]);

        TripStatusHistory::query()->create([
            'tenant_id' => $trip->tenant_id,
            'trip_id' => $trip->id,
            'from_status' => TripStatus::Completed->value,
            'to_status' => TripStatus::Invoiced->value,
            'changed_at' => now(),
            'changed_by' => $user?->id,
            'remarks' => 'Invoice generated',
        ]);
    }

    private function revertTripFromInvoicedIfNeeded(
        Trip $trip,
        ?User $user = null,
        string $historyRemarks = 'Invoice cancelled',
    ): void {
        $status = $trip->status instanceof TripStatus
            ? $trip->status
            : TripStatus::tryFrom((string) $trip->status);

        if ($status !== TripStatus::Invoiced) {
            return;
        }

        $hasActiveInvoices = Invoice::query()
            ->withoutGlobalScopes()
            ->where('trip_id', $trip->id)
            ->whereNull('deleted_at')
            ->where('payment_status', '!=', InvoicePaymentStatus::Cancelled->value)
            ->exists();

        if ($hasActiveInvoices) {
            return;
        }

        $trip->update(['status' => TripStatus::Completed]);

        TripStatusHistory::query()->create([
            'tenant_id' => $trip->tenant_id,
            'trip_id' => $trip->id,
            'from_status' => TripStatus::Invoiced->value,
            'to_status' => TripStatus::Completed->value,
            'changed_at' => now(),
            'changed_by' => $user?->id,
            'remarks' => $historyRemarks,
        ]);
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

    private function assertInvoiceChargesEditable(Invoice $invoice): void
    {
        if ($invoice->trashed()) {
            throw ValidationException::withMessages([
                'invoice' => ['Archived invoices cannot be updated.'],
            ]);
        }

        if ($invoice->isCancelled()) {
            throw ValidationException::withMessages([
                'invoice' => ['Cancelled invoices cannot be updated.'],
            ]);
        }

        if ($invoice->payment_status !== InvoicePaymentStatus::Pending) {
            throw ValidationException::withMessages([
                'invoice' => ['Only pending invoices can be updated.'],
            ]);
        }

        if ($invoice->hasRecordedPayments()) {
            throw ValidationException::withMessages([
                'invoice' => ['Invoices with recorded payments cannot be updated.'],
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
    private function resolveGstSettingsForInvoice(Invoice $invoice): array
    {
        $branch = Branch::query()
            ->with(['company.state', 'state'])
            ->find($invoice->branch_id);

        if ($branch === null) {
            throw ValidationException::withMessages([
                'branch' => ['Branch context is required to update invoices.'],
            ]);
        }

        return $this->gstSettingsResolver->forBranch($branch);
    }

    /**
     * @return list<array{description: string, amount: float}>
     */
    private function normalizeCustomItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $description = trim((string) ($item['description'] ?? ''));
            $amount = round((float) ($item['amount'] ?? 0), 2);

            if ($description === '' || $amount < 0.01) {
                continue;
            }

            $normalized[] = [
                'description' => $description,
                'amount' => $amount,
            ];
        }

        return $normalized;
    }

    private function resolveParticularsMode(array $options, string $customerId): InvoiceParticularsMode
    {
        $groupMode = $options['groups'][$customerId]['particulars_mode'] ?? null;
        $tripMode = $options['particulars_mode'] ?? null;
        $value = is_string($groupMode) && $groupMode !== ''
            ? $groupMode
            : (is_string($tripMode) && $tripMode !== '' ? $tripMode : InvoiceParticularsMode::Separate->value);

        return InvoiceParticularsMode::tryFrom($value) ?? InvoiceParticularsMode::Separate;
    }

    /**
     * @param  list<array<string, mixed>>  $routes
     * @return list<array<string, mixed>>
     */
    private function buildInvoiceLines(array $routes, InvoiceParticularsMode $mode): array
    {
        if ($mode === InvoiceParticularsMode::Separate || count($routes) <= 1) {
            return array_map(function (array $route): array {
                return [
                    ...$route,
                    'route_leg_ids' => [$route['id']],
                    'route_legs_snapshot' => [$route],
                ];
            }, $routes);
        }

        return [$this->combineRouteLines($routes)];
    }

    /**
     * @param  list<array<string, mixed>>  $routes
     * @return array<string, mixed>
     */
    private function combineRouteLines(array $routes): array
    {
        $sorted = collect($routes)->sortBy('sequence_no')->values();
        $first = $sorted->first();
        $destinations = $sorted->pluck('to_location')->filter()->unique()->values();

        $toLocation = $destinations->count() <= 1
            ? (string) ($destinations->first() ?? $sorted->last()['to_location'] ?? '')
            : $destinations->slice(0, -1)->implode(', ').' and '.$destinations->last();

        $goodsNames = $sorted->pluck('goods_name')->filter()->unique()->values();

        return [
            'id' => $first['id'],
            'route_leg_ids' => $sorted->pluck('id')->values()->all(),
            'sequence_no' => $first['sequence_no'],
            'from_location' => $first['from_location'],
            'to_location' => $toLocation,
            'consignor' => $first['consignor'] ?? null,
            'consignee' => $first['consignee'] ?? null,
            'from_address' => $first['from_address'] ?? null,
            'to_address' => $first['to_address'] ?? null,
            'goods_name' => $goodsNames->isNotEmpty()
                ? $goodsNames->implode(', ')
                : ($first['goods_name'] ?? null),
            'weight_ton' => round((float) $sorted->sum(fn (array $route): float => (float) $route['weight_ton']), 3),
            'freight_amount' => round((float) $sorted->sum(fn (array $route): float => (float) $route['freight_amount']), 2),
            'route_legs_snapshot' => $sorted->values()->all(),
        ];
    }
}
