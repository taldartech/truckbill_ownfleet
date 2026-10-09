<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $invoice->document_type?->label() ?? 'Invoice' }} - {{ $invoice->invoice_number ?? $invoice->id }}</title>
    <style>
        @page {
            margin: 12px 10px;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9pt;
            margin: 0;
            padding: 0;
        }

        .invoice-card {
            border: 1.5px solid #000;
            overflow: hidden;
        }

        .invoice-body {
            padding: 0 12px 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .header {
            padding: 14px 16px 12px;
            background: {{ $document_color }};
            color: {{ $document_text_color }};
        }

        .header td {
            border: none;
            vertical-align: top;
            padding: 0;
        }

        .company-name {
            font-size: 15pt;
            font-weight: 700;
            letter-spacing: 0.2px;
            line-height: 1.2;
            margin: 0 0 4px;
            text-transform: uppercase;
        }

        .company-address {
            font-size: 8.5pt;
            line-height: 1.35;
            margin: 0 0 6px;
            opacity: 0.95;
        }

        .company-tax {
            font-size: 8.5pt;
            margin: 0;
        }

        .invoice-meta {
            text-align: right;
            white-space: nowrap;
        }

        .invoice-number {
            font-size: 13pt;
            font-weight: 700;
            line-height: 1.2;
            margin: 0 0 4px;
        }

        .invoice-date {
            font-size: 9pt;
            margin: 0;
        }

        .doc-title {
            text-align: center;
            font-size: 16pt;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin: 12px 0 10px;
        }

        .invoice-to {
            border: 1px solid #000;
            padding: 8px 10px;
            margin-bottom: 10px;
        }

        .invoice-to-label {
            font-weight: 700;
            margin-bottom: 2px;
        }

        .invoice-to-name {
            font-weight: 700;
            font-size: 10pt;
            margin-bottom: 2px;
        }

        .invoice-to-line {
            font-size: 8.5pt;
            line-height: 1.35;
        }

        .section-table {
            margin-bottom: 10px;
        }

        .section-table th,
        .section-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: top;
            font-size: 8.5pt;
            line-height: 1.35;
        }

        .section-table th {
            font-weight: 700;
            text-align: left;
            text-transform: uppercase;
            font-size: 8.5pt;
            letter-spacing: 0.3px;
            background: {{ $document_color }};
            color: {{ $document_text_color }};
        }

        .details-table {
            margin-bottom: 10px;
        }

        .details-table .details-head th {
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 5px 6px;
            border: 1px solid #000;
            font-size: 8.5pt;
            background: {{ $document_color }};
            color: {{ $document_text_color }};
        }

        .details-table td {
            border: 1px solid #000;
            padding: 5px 6px;
            font-size: 8.5pt;
            vertical-align: middle;
        }

        .details-label {
            font-weight: 700;
            width: 16%;
            color: {{ $document_color }};
            background: {{ $document_color_soft }};
        }

        .details-value {
            width: 34%;
        }

        .lines-table {
            margin-bottom: 8px;
        }

        .lines-table th,
        .lines-table td {
            border: 1px solid #000;
            padding: 5px 4px;
            font-size: 7.5pt;
            vertical-align: middle;
        }

        .lines-table th {
            font-weight: 700;
            text-align: center;
            font-size: 7.2pt;
            line-height: 1.15;
            background: {{ $document_color }};
            color: {{ $document_text_color }};
        }

        .lines-table .c-center {
            text-align: center;
        }

        .lines-table .c-right {
            text-align: right;
            white-space: nowrap;
        }

        .lines-table .c-left {
            text-align: left;
        }

        .page-total-label {
            font-weight: 700;
            text-align: right;
        }

        .page-total-value {
            font-weight: 700;
            text-align: right;
            white-space: nowrap;
        }

        .deduction-label {
            font-weight: 600;
            text-align: right;
            color: #b91c1c;
        }

        .deduction-value {
            font-weight: 600;
            text-align: right;
            white-space: nowrap;
            color: #b91c1c;
        }

        .tds-label {
            font-weight: 600;
            text-align: right;
            color: #1d4ed8;
        }

        .tds-value {
            font-weight: 600;
            text-align: right;
            white-space: nowrap;
            color: #1d4ed8;
        }

        .net-total-row td {
            font-weight: 800;
            background: {{ $document_color_soft }};
        }

        .balance-paid {
            color: #15803d;
        }

        .balance-due {
            color: #b91c1c;
        }

        .footer-note {
            font-size: 8pt;
            line-height: 1.4;
            margin-top: 8px;
        }

        .amount-words {
            font-size: 8.5pt;
            margin-top: 6px;
        }

        .signature {
            text-align: right;
            font-size: 8.5pt;
            font-weight: 700;
            margin-top: 18px;
        }

        .brand-footer {
            font-size: 7px;
            text-align: right;
            margin-top: 4px;
        }
    </style>
</head>

<body>
    @php
        use App\Enums\InvoiceDocumentType;
        use App\Enums\InvoiceParticularsMode;
        use App\Enums\LoadCategory;
        use App\Enums\TaxLiability;
        use App\Modules\TripManagement\Support\LoadClassifier;

        $company = $company ?? $invoice->tenant;
        $branch = $invoice->branch;
        $customer = $invoice->customer;
        $trip = $invoice->trip;
        $items = ($invoice->items ?? collect())->values();

        $documentType =
            $invoice->document_type instanceof InvoiceDocumentType
                ? $invoice->document_type
                : InvoiceDocumentType::tryFrom((string) $invoice->document_type);

        $isRcm =
            $documentType === InvoiceDocumentType::TaxInvoiceRcm ||
            $invoice->tax_liability === TaxLiability::ReverseCharge ||
            ($invoice->tax_liability?->value ?? $invoice->tax_liability) === 'reverse_charge';

        $displaysGst = $documentType?->displaysGst() ?? false;
        $collectsGst = $documentType?->collectsGst() ?? false;

        $cgst = (float) ($invoice->cgst_amount ?? 0);
        $sgst = (float) ($invoice->sgst_amount ?? 0);
        $igst = (float) ($invoice->igst_amount ?? 0);
        $cgstRate = (float) ($invoice->cgst_rate ?? 0);
        $sgstRate = (float) ($invoice->sgst_rate ?? 0);
        $igstRate = (float) ($invoice->igst_rate ?? 0);
        $baseAmount = (float) ($invoice->freight_amount ?? 0);
        $grandTotal = (float) ($invoice->total_amount ?? $baseAmount);
        $totalGst = $cgst + $sgst + $igst;

        $documentTitle = strtoupper($documentType?->label() ?? 'Invoice');
        $billNumber = $invoice->invoice_number ?? $invoice->id;
        $invoiceDate = $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y') : '—';

        $companyName = $company->name ?? '—';
        $companyAddress = trim(
            (string) (($branch && filled($branch->branch_address) ? $branch->branch_address : null) ??
                ($company->address ?? '')),
        );
        if ($company->state?->name && !str_contains(strtolower($companyAddress), strtolower($company->state->name))) {
            $companyAddress = trim($companyAddress . ($companyAddress !== '' ? ' ' : '') . $company->state->name);
        }

        $gstNumber =
            $branch && !($branch->inherit_company_gst ?? true) && filled($branch->gst_number)
                ? $branch->gst_number
                : $company->gst_number ?? null;
        $panNumber = $company->pan_number ?? null;

        $customerAddressParts = array_filter([$customer?->address, $customer?->city?->name, $customer?->state?->name]);
        $customerAddress = implode(', ', $customerAddressParts) ?: '—';

        $particularsMode =
            $invoice->particulars_mode instanceof InvoiceParticularsMode
                ? $invoice->particulars_mode
                : InvoiceParticularsMode::tryFrom((string) ($invoice->particulars_mode ?? '')) ??
                    InvoiceParticularsMode::Separate;

        $itemRouteLegs = $items->map(fn($item) => $item->routeLeg)->filter()->values();

        if ($particularsMode === InvoiceParticularsMode::Combined && $trip && $invoice->customer_id) {
            $routeLegs = ($trip->relationLoaded('routeLegs') ? $trip->routeLegs : collect())
                ->filter(fn($leg) => (int) $leg->customer_id === (int) $invoice->customer_id)
                ->filter(fn($leg) => (float) $leg->freight_amount > 0)
                ->sortBy('sequence_no')
                ->values();
        } else {
            $routeLegs = $itemRouteLegs->sortBy(fn($leg) => $leg->sequence_no)->values();
        }

        $formatStationLocation = function ($leg, string $side): string {
            $station = $side === 'from' ? $leg->fromStation : $leg->toStation;
            $stationName = trim(
                (string) ($station?->name ?: ($side === 'from' ? $leg->from_city : $leg->to_city) ?: ''),
            );
            $cityName = trim(
                (string) ($station?->city?->name ?: ($side === 'from' ? $leg->from_city : $leg->to_city) ?: ''),
            );
            $stateCode = strtoupper(trim((string) ($station?->state?->code ?: '')));

            $parts = [];

            if ($stationName !== '') {
                $parts[] = $stationName;
            }

            if (
                $cityName !== '' &&
                !collect($parts)->contains(
                    fn(string $part): bool => str_contains(mb_strtolower($part), mb_strtolower($cityName)),
                )
            ) {
                $parts[] = $cityName;
            }

            if ($stateCode !== '') {
                $parts[] = $stateCode;
            }

            return implode(', ', $parts);
        };

        $formatPartyWithLocation = function (?string $name, ?string $location): ?string {
            $name = trim((string) $name);
            $location = trim((string) $location);

            if ($name === '' && $location === '') {
                return null;
            }

            if ($name === '') {
                return $location;
            }

            if ($location === '') {
                return $name;
            }

            if (str_contains(mb_strtolower($name), mb_strtolower($location))) {
                return $name;
            }

            return $name . ', ' . $location;
        };

        $formatConsignor = function ($leg) use ($formatPartyWithLocation, $formatStationLocation): ?string {
            $location = filled($leg->from_address)
                ? (string) $leg->from_address
                : $formatStationLocation($leg, 'from');

            return $formatPartyWithLocation($leg->consignor, $location);
        };

        $formatConsignee = function ($leg) use ($formatPartyWithLocation, $formatStationLocation): ?string {
            $location = filled($leg->to_address)
                ? (string) $leg->to_address
                : $formatStationLocation($leg, 'to');

            return $formatPartyWithLocation($leg->consignee, $location);
        };

        $consignors = $routeLegs
            ->map(fn($leg) => $formatConsignor($leg))
            ->filter()
            ->unique()
            ->values();

        if ($consignors->isEmpty()) {
            $consignors = $items
                ->map(fn($item) => $formatPartyWithLocation(null, $item->from_location))
                ->filter()
                ->unique()
                ->values();
        }

        $consignees = $routeLegs
            ->map(fn($leg) => $formatConsignee($leg))
            ->filter()
            ->unique()
            ->values();

        if ($consignees->isEmpty()) {
            $consignees = $items
                ->map(fn($item) => $formatPartyWithLocation(null, $item->to_location))
                ->filter()
                ->unique()
                ->values();
        }

        $originParts = $routeLegs->map(fn($leg) => $formatStationLocation($leg, 'from'))->filter()->unique()->values();
        $origin = $originParts->isNotEmpty()
            ? $originParts->implode(', ')
            : ($items->pluck('from_location')->filter()->unique()->values()->implode(', ') ?:
            '—');

        $destinationParts = $routeLegs
            ->map(fn($leg) => $formatStationLocation($leg, 'to'))
            ->filter()
            ->unique()
            ->values();
        $destination = $destinationParts->isNotEmpty()
            ? $destinationParts
            : $items->pluck('to_location')->filter()->unique()->values();
        $destinationLabel =
            $destination->count() > 1
                ? $destination->slice(0, -1)->implode(', ') . ' and ' . $destination->last()
                : ($destination->first() ?:
                '—');

        $pickupDates = $routeLegs
            ->map(fn($leg) => $leg->pickup_date)
            ->filter()
            ->map(fn($date) => \Carbon\Carbon::parse($date)->format('F j, Y'))
            ->unique()
            ->values();

        if ($pickupDates->isEmpty() && $trip?->start_at) {
            $pickupDates = collect([\Carbon\Carbon::parse($trip->start_at)->format('F j, Y')]);
        }

        $deliveryDates = collect();
        if ($trip?->end_at) {
            $deliveryDates = collect([\Carbon\Carbon::parse($trip->end_at)->format('F j, Y')]);
        } elseif ($trip?->actual_arrival_at) {
            $deliveryDates = collect([\Carbon\Carbon::parse($trip->actual_arrival_at)->format('F j, Y')]);
        }

        $formatDateList = function ($dates) {
            $dates = collect($dates)->filter()->values();
            if ($dates->isEmpty()) {
                return '—';
            }
            if ($dates->count() === 1) {
                return $dates->first();
            }

            return $dates->slice(0, -1)->implode(', ') . ' and ' . $dates->last();
        };

        $vehicleNo = optional($trip?->vehicle)->registration_number ?? '—';
        $driverName = optional($trip?->driver)->name ?? '—';

        $goodsNames = $items
            ->filter(fn ($item) => $item->trip_route_leg_id !== null)
            ->pluck('goods_name')
            ->filter()
            ->unique()
            ->values();
        $freightDescription = $goodsNames->implode(', ') ?: 'Freight';

        $loadType = '—';
        if ($trip !== null) {
            try {
                $weightTon = (float) ($trip->load_weight_ton ?? $items->sum('weight_ton'));
                $maxLoadTon = (float) ($trip->max_load_ton ?? 0);
                $classification = LoadClassifier::classify($weightTon, $maxLoadTon);
                $loadType = match ($classification->category) {
                    LoadCategory::Empty => 'Empty',
                    LoadCategory::Half => 'Half load',
                    LoadCategory::Full => 'Full-truck load',
                };
            } catch (\Throwable) {
                $loadType = ((float) $items->sum('weight_ton')) > 0 ? 'Full-truck load' : 'Empty';
            }
        }

        $hsnCode = '996511';

        $formatMoney = function (float $amount, bool $withSuffix = false): string {
            $formatted = number_format($amount, $amount == floor($amount) ? 0 : 2);

            return $withSuffix ? $formatted . '/-' : $formatted;
        };

        $formatGstCell = function (float $rate, float $invoiceGstAmount) use ($displaysGst): string {
            if (!$displaysGst || $rate <= 0 || $invoiceGstAmount <= 0) {
                return '—';
            }

            return rtrim(rtrim(number_format($rate, 2), '0'), '.') . '%';
        };

        $lineRows = [];
        $itemCount = $items->count();
        $allocatedCgst = 0.0;
        $allocatedSgst = 0.0;
        $allocatedIgst = 0.0;

        foreach ($items as $index => $item) {
            $serviceCharge = round((float) $item->freight_amount, 2);
            $share = $baseAmount > 0 ? $serviceCharge / $baseAmount : ($itemCount > 0 ? 1 / $itemCount : 0);
            $isLast = $index === $itemCount - 1;

            if ($isLast) {
                $lineCgst = round($cgst - $allocatedCgst, 2);
                $lineSgst = round($sgst - $allocatedSgst, 2);
                $lineIgst = round($igst - $allocatedIgst, 2);
            } else {
                $lineCgst = round($cgst * $share, 2);
                $lineSgst = round($sgst * $share, 2);
                $lineIgst = round($igst * $share, 2);
                $allocatedCgst += $lineCgst;
                $allocatedSgst += $lineSgst;
                $allocatedIgst += $lineIgst;
            }

            $taxableGst = $lineCgst + $lineSgst + $lineIgst;
            $lineTotal = $collectsGst ? $serviceCharge + $taxableGst : $serviceCharge;

            if ($item->trip_route_leg_id === null) {
                $description = filled($item->goods_name)
                    ? (string) $item->goods_name
                    : 'Additional charge';
            } else {
                $description =
                    $index === 0
                        ? 'Freight Charges'
                        : 'Extra Stop' . ($item->to_location ? ' — ' . $item->to_location : '');
            }

            $lineRows[] = [
                'sno' => $index + 1,
                'description' => $description,
                'hsn' => $index === 0 ? $hsnCode : '',
                'service_charge' => $serviceCharge,
                'cgst' => $formatGstCell($cgstRate, $cgst),
                'sgst' => $formatGstCell($sgstRate, $sgst),
                'igst' => $formatGstCell($igstRate, $igst),
                'taxable' => $displaysGst ? $taxableGst : 0,
                'total' => $lineTotal,
            ];
        }

        if ($lineRows === [] && $baseAmount > 0) {
            $lineRows[] = [
                'sno' => 1,
                'description' => 'Freight Charges',
                'hsn' => $hsnCode,
                'service_charge' => $baseAmount,
                'cgst' => $formatGstCell($cgstRate, $cgst),
                'sgst' => $formatGstCell($sgstRate, $sgst),
                'igst' => $formatGstCell($igstRate, $igst),
                'taxable' => $displaysGst ? $totalGst : 0,
                'total' => $grandTotal,
            ];
        }

        $payments = ($invoice->relationLoaded('payments') && $invoice->payments->isNotEmpty())
            ? $invoice->payments
            : $invoice->payments()->withoutGlobalScopes()->where('invoice_id', $invoice->id)->get();

        $totalClaims = round((float) $payments->sum(fn ($payment) => (float) ($payment->deduction_amount ?? 0)), 2);
        $totalTds = round((float) $payments->sum(fn ($payment) => (float) ($payment->tds_amount ?? 0)), 2);
        $totalReceived = round((float) $payments->sum(fn ($payment) => (float) ($payment->amount ?? 0)), 2);
        $hasDeductions = ($totalClaims > 0 || $totalTds > 0);

        $deductionReasons = $payments->where('deduction_amount', '>', 0)
            ->pluck('deduction_reason')
            ->map(fn ($r) => trim((string) $r))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $deductionReasonText = !empty($deductionReasons) ? ' (' . implode(', ', $deductionReasons) . ')' : '';

        $netReceivable = max(0, round($grandTotal - $totalClaims - $totalTds, 2));
        $balanceDue = max(0, round($grandTotal - (float) ($invoice->paid_amount ?? 0), 2));

        $grandTotalWords = $grandTotal > 0 ? ucfirst(getIndianCurrency($grandTotal)) . ' Only' : 'Zero Rupees Only';
        $netReceivableWords = $netReceivable > 0 ? ucfirst(getIndianCurrency($netReceivable)) . ' Only' : 'Zero Rupees Only';
    @endphp

    <div class="invoice-card">
        <div class="header">
            <table>
                <tr>
                    <td style="width: 68%;">
                        <div class="company-name">{{ $companyName }}</div>
                        @if ($companyAddress !== '')
                            <div class="company-address">{{ $companyAddress }}</div>
                        @endif
                        <div class="company-tax">
                            GST: {{ $gstNumber ?: '—' }}
                            @if ($panNumber)
                                | PAN: {{ $panNumber }}
                            @endif
                        </div>
                    </td>
                    <td style="width: 32%;" class="invoice-meta">
                        <div class="invoice-number">{{ $billNumber }}</div>
                        <div class="invoice-date">Date: {{ $invoiceDate }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="invoice-body">
            <div class="doc-title">{{ $documentTitle }}</div>

            <div class="invoice-to">
                <div class="invoice-to-label">Invoice To:</div>
                <div class="invoice-to-name">{{ $customer?->name ?? '—' }}</div>
                <div class="invoice-to-line">{{ $customerAddress }}</div>
                <div class="invoice-to-line">GSTIN {{ $customer?->gst_number ?: '—' }}</div>
            </div>

            <table class="section-table">
                <tr>
                    <th style="width: 50%;">Consignor (From)</th>
                    <th style="width: 50%;">Consignee (To)</th>
                </tr>
                <tr>
                    <td>
                        @forelse ($consignors as $index => $consignor)
                            <div>{{ $consignors->count() > 1 ? $index + 1 . ' - ' : '' }}{{ $consignor }}</div>
                        @empty
                            <div class="muted">—</div>
                        @endforelse
                    </td>
                    <td>
                        @forelse ($consignees as $index => $consignee)
                            <div>{{ $consignees->count() > 1 ? $index + 1 . ' - ' : '' }}{{ $consignee }}</div>
                        @empty
                            <div class="muted">—</div>
                        @endforelse
                    </td>
                </tr>
            </table>

            <table class="details-table">
                <tr class="details-head">
                    <th colspan="4">Details</th>
                </tr>
                <tr>
                    <td class="details-label">Vehicle No.</td>
                    <td class="details-value">{{ $vehicleNo }}</td>
                    <td class="details-label">Load Type</td>
                    <td class="details-value">{{ $loadType }}</td>
                </tr>
                <tr>
                    <td class="details-label">Origin</td>
                    <td class="details-value">{{ $origin }}</td>
                    <td class="details-label">Destination</td>
                    <td class="details-value">{{ $destinationLabel }}</td>
                </tr>
                <tr>
                    <td class="details-label">Pickup Date</td>
                    <td class="details-value">{{ $formatDateList($pickupDates) }}</td>
                    <td class="details-label">Delivery Date</td>
                    <td class="details-value">{{ $formatDateList($deliveryDates) }}</td>
                </tr>
                <tr>
                    <td class="details-label">Freight Description</td>
                    <td class="details-value">{{ $freightDescription }}</td>
                    <td class="details-label">Driver</td>
                    <td class="details-value">{{ $driverName }}</td>
                </tr>
            </table>

            <table class="lines-table">
                <thead>
                    <tr>
                        <th style="width: 6%;">S.No.</th>
                        <th style="width: 18%;">Description</th>
                        <th style="width: 9%;">HSN</th>
                        <th style="width: 12%;">Service Charge</th>
                        <th style="width: 9%;">CGST</th>
                        <th style="width: 9%;">SGST</th>
                        <th style="width: 9%;">IGST</th>
                        <th style="width: 13%;">Taxable Amount</th>
                        <th style="width: 15%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lineRows as $row)
                        <tr>
                            <td class="c-center">{{ $row['sno'] }}.</td>
                            <td class="c-left">{{ $row['description'] }}</td>
                            <td class="c-center">{{ $row['hsn'] }}</td>
                            <td class="c-right">{{ $formatMoney($row['service_charge']) }}</td>
                            <td class="c-center">{{ $row['cgst'] }}</td>
                            <td class="c-center">{{ $row['sgst'] }}</td>
                            <td class="c-center">{{ $row['igst'] }}</td>
                            <td class="c-right">
                                @if ($displaysGst && $row['taxable'] > 0)
                                    {{ $formatMoney($row['taxable'], true) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="c-right">{{ $formatMoney($row['total'], true) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="c-center muted">No line items</td>
                        </tr>
                    @endforelse
                    <tr>
                        <td colspan="8" class="page-total-label">Total Amount</td>
                        <td class="page-total-value">{{ $formatMoney($grandTotal, true) }}</td>
                    </tr>
                    @if ($totalClaims > 0)
                        <tr>
                            <td colspan="8" class="deduction-label">Less: Shortage / Discount{{ $deductionReasonText }}</td>
                            <td class="deduction-value">- {{ $formatMoney($totalClaims, true) }}</td>
                        </tr>
                    @endif
                    @if ($totalTds > 0)
                        <tr>
                            <td colspan="8" class="tds-label">Less: TDS u/s 194C</td>
                            <td class="tds-value">- {{ $formatMoney($totalTds, true) }}</td>
                        </tr>
                    @endif
                    @if ($hasDeductions)
                        <tr class="net-total-row">
                            <td colspan="8" class="page-total-label" style="font-weight: 800;">Net Amount Receivable</td>
                            <td class="page-total-value" style="font-weight: 800;">{{ $formatMoney($netReceivable, true) }}</td>
                        </tr>
                    @endif
                    @if ($totalReceived > 0)
                        <tr>
                            <td colspan="8" class="page-total-label" style="font-weight: 600;">Amount Received</td>
                            <td class="page-total-value" style="font-weight: 600;">{{ $formatMoney($totalReceived, true) }}</td>
                        </tr>
                    @endif
                    @if ($hasDeductions || $totalReceived > 0)
                        <tr>
                            <td colspan="8" class="page-total-label">Balance Due</td>
                            <td class="page-total-value {{ $balanceDue <= 0 ? 'balance-paid' : 'balance-due' }}">{{ $formatMoney($balanceDue, true) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <div class="amount-words">
                @if ($hasDeductions && $netReceivable != $grandTotal)
                    <div><strong>Invoice Total in words:</strong> {{ $grandTotalWords }}</div>
                    <div style="margin-top: 3px;"><strong>Net Amount in words:</strong> {{ $netReceivableWords }}</div>
                @else
                    <strong>Amount in words:</strong> {{ $grandTotalWords }}
                @endif
            </div>

            @if ($isRcm)
                <div class="footer-note">
                    <strong>Note:</strong> GST Liability of Debtor: {{ $customer?->name }} under Reverse Charge
                    Mechanism of GST Act, 2017.
                </div>
            @endif

            <div class="signature">
                For {{ $companyName }}<br><br><br>
                Authorized Signature
            </div>
        </div>
    </div>
    <div class="brand-footer" style="font-size: 7px; text-align: right; margin-top: 3px; font-weight: bold; color: #475569;">Digitally Managed by TruckBill (www.truckbill.in)</div>

    <script type="text/php">
        if (isset($pdf)) {
            $text = "Page {PAGE_NUM} / {PAGE_COUNT}";
            $size = 8;
            $font = $fontMetrics->getFont("Arial");
            $x = 520;
            $y = $pdf->get_height() - 18;
            $pdf->page_text($x, $y, $text, $font, $size);
        }
    </script>
</body>

</html>
