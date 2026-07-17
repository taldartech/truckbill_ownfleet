<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{{ $invoice->document_type?->label() ?? 'Invoice' }} - {{ $invoice->invoice_number ?? $invoice->id }}</title>
    <style>
        @page { margin: 14px 12px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9pt;
            margin: 0;
            padding: 0;
        }
        .invoice-card {
            border: 1px solid #231f20;
            border-radius: 8px;
            overflow: hidden;
        }
        .top-band {
            padding: 10px 14px;
            border-bottom: 2px solid #231f20;
        }
        .invoice-title {
            font-size: 23px;
            font-weight: 800;
            letter-spacing: 1px;
            margin: 0;
            line-height: 1;
        }
        .company-name {
            font-size: 18px;
            font-weight: 700;
            margin-top: 4px;
            margin-bottom: 2px;
        }
        .company-meta {
            font-size: 10px;
            opacity: 0.95;
        }
        .company-logo {
            width: 92px;
            height: 58px;
            object-fit: contain;
            display: block;
        }
        .section {
            padding: 10px 12px;
        }
        .chip-title {
            border: 1px solid #231f20;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }
        th, td {
            border: 1px solid #231f20;
            padding: 5px 4px;
            font-size: 8.3pt;
            word-wrap: break-word;
            overflow-wrap: break-word;
            vertical-align: top;
        }
        .lines-table {
            page-break-inside: auto;
            table-layout: fixed;
        }
        .lines-table th,
        .lines-table td {
            padding: 2px 2px;
            font-size: 6.3pt !important;
            line-height: 1.15;
            vertical-align: middle;
            word-wrap: normal;
            overflow-wrap: normal;
        }
        .lines-table th {
            font-size: 6.5pt !important;
            font-weight: 700;
            text-align: center;
            white-space: normal;
            line-height: 1.1;
            padding: 3px 1px;
        }
        .lines-table .c-seq,
        .lines-table .c-date,
        .lines-table .c-nowrap {
            white-space: nowrap;
            text-align: center;
        }
        .lines-table .c-num,
        .lines-table .c-money {
            white-space: nowrap;
            text-align: right;
        }
        .lines-table .c-text {
            font-size: 6.8pt;
            line-height: 1.1;
            word-break: break-all;
        }
        th {
            font-weight: 700;
        }
        .meta-label {
            width: 32%;
            font-weight: 700;
        }
        .summary-table .grand td {
            font-weight: 700;
        }
        .footer-note {
            font-size: 9px;
            line-height: 1.4;
        }
        .signature-box {
            text-align: center;
            font-weight: 700;
        }
    </style>
</head>
<body>
    @php
        use App\Enums\InvoiceDocumentType;
        use App\Enums\TaxLiability;

        $company = $company ?? $invoice->tenant;
        $customer = $invoice->customer;
        $items = $invoice->items ?? collect();
        $cgst = (float) ($invoice->cgst_amount ?? 0);
        $sgst = (float) ($invoice->sgst_amount ?? 0);
        $igst = (float) ($invoice->igst_amount ?? 0);
        $baseAmount = (float) ($invoice->freight_amount ?? 0);
        $documentType = $invoice->document_type instanceof InvoiceDocumentType
            ? $invoice->document_type
            : InvoiceDocumentType::tryFrom((string) $invoice->document_type);
        $isRcm = $documentType === InvoiceDocumentType::TaxInvoiceRcm
            || $invoice->tax_liability === TaxLiability::ReverseCharge
            || ($invoice->tax_liability?->value ?? $invoice->tax_liability) === 'reverse_charge';
        $grandTotal = (float) ($invoice->total_amount ?? $baseAmount);
        $billNumber = $invoice->invoice_number ?? $invoice->id;
        $totalGst = $cgst + $sgst + $igst;
        $documentTitle = strtoupper($documentType?->label() ?? 'Invoice');
        $truckNumber = optional($invoice->trip?->vehicle)->registration_number
            ?? optional($invoice->trip)->vehicle?->registration_number
            ?? '-';
        $invoiceDate = $invoice->invoice_date
            ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d-m-Y')
            : '-';
        $placeOfSupply = $invoice->customerState?->code
            ?? $customer?->state?->code
            ?? $invoice->supplierState?->code
            ?? '—';
        $logoOwner = $invoice->branch ?? $company;
        $logoPath = $logoOwner?->logoFilesystemPathForPdf();
        $colWidths = ['5%', '14%', '14%', '28%', '14%', '12%', '13%'];
    @endphp

    <div class="invoice-card">
        <div class="top-band">
            <table style="width: 100%; border-collapse: collapse; border: none; margin: 0; table-layout: auto;">
                <tr>
                    @if ($logoPath)
                        <td style="width: 120px; padding: 0; border: none; vertical-align: middle;">
                            <img
                                src="{{ $logoPath }}"
                                alt="{{ $company->name }} Logo"
                                class="company-logo"
                            >
                        </td>
                    @endif
                    <td style="padding: 0; border: none; vertical-align: middle;">
                        <p class="invoice-title">{{ $documentTitle }}</p>
                        <div class="company-name">{{ $company->name }}</div>
                        <div class="company-meta">
                            {{ $company->address }}
                            @if ($company->state?->name)
                                {{ $company->state->name }}
                            @endif
                        </div>
                        <div class="company-meta">
                            GSTIN: {{ $company->gst_number ?? '—' }} | PAN: {{ $company->pan_number ?? '—' }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="chip-title">Billing Details</div>
            <table class="meta-table">
                <tr>
                    <td width="52%">
                        <div class="muted">Bill To</div>
                        <div style="font-weight:700; font-size:10px;">{{ $customer?->name }}</div>
                        <div>
                            @if ($customer?->city?->name || $customer?->state?->name)
                                {{ $customer?->city?->name }}{{ $customer?->city?->name && $customer?->state?->name ? ', ' : '' }}{{ $customer?->state?->name }}
                            @endif
                        </div>
                        <div>GSTIN: {{ $customer?->gst_number ?? '—' }}</div>
                        <div>PAN: {{ $customer?->pan_number ?? '—' }}</div>
                    </td>
                    <td width="48%">
                        <table>
                            <tr>
                                <td class="meta-label">Invoice No.</td>
                                <td>{{ $billNumber }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Invoice Date</td>
                                <td>{{ $invoiceDate }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Reverse Charge</td>
                                <td>{{ $isRcm ? 'Yes' : 'No' }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">SAC Code</td>
                                <td>996511</td>
                            </tr>
                            @if ($invoice->due_date)
                                <tr>
                                    <td class="meta-label">Due Date</td>
                                    <td>{{ \Carbon\Carbon::parse($invoice->due_date)->format('d-m-Y') }}</td>
                                </tr>
                            @endif
                        </table>
                    </td>
                </tr>
            </table>

            <div class="chip-title">Transportation Bill</div>
            <table class="lines-table">
                <tr>
                    <th width="{{ $colWidths[0] }}" style="width:{{ $colWidths[0] }}">#</th>
                    <th width="{{ $colWidths[1] }}" style="width:{{ $colWidths[1] }}">Truck</th>
                    <th width="{{ $colWidths[2] }}" style="width:{{ $colWidths[2] }}">From</th>
                    <th width="{{ $colWidths[3] }}" style="width:{{ $colWidths[3] }}">To</th>
                    <th width="{{ $colWidths[4] }}" style="width:{{ $colWidths[4] }}">Goods</th>
                    <th width="{{ $colWidths[5] }}" style="width:{{ $colWidths[5] }}">Weight</th>
                    <th width="{{ $colWidths[6] }}" style="width:{{ $colWidths[6] }}">Amount</th>
                </tr>
                @foreach ($items as $index => $item)
                    <tr>
                        <td class="c-seq">{{ $index + 1 }}</td>
                        <td class="c-nowrap">{{ $truckNumber }}</td>
                        <td class="c-text">{{ $item->from_location }}</td>
                        <td class="c-text">{{ $item->to_location }}</td>
                        <td class="c-text">{{ $item->goods_name ?: '—' }}</td>
                        <td class="c-num">{{ number_format((float) $item->weight_ton, 3) }}</td>
                        <td class="c-money">{{ number_format((float) $item->freight_amount, 2) }}</td>
                    </tr>
                @endforeach
            </table>

            <table>
                <tr>
                    <td width="62%">
                        <div class="chip-title" style="margin-bottom:6px;">Payment Details</div>
                        <div class="footer-note">
                            <strong>Place of Supply:</strong> {{ $placeOfSupply }}<br>
                            @if ($isRcm)
                                <strong>Note:</strong> GST Liability of Debtor:
                                {{ $customer?->name }} under Reverse Charge Mechanism of GST Act, 2017.
                            @endif
                        </div>
                    </td>
                    <td width="38%">
                        <table class="summary-table">
                            <tr><td>Base Amount</td><td>{{ number_format($baseAmount, 2) }}</td></tr>
                            @if ($documentType?->displaysGst())
                                @if ($cgst > 0)
                                    <tr><td>CGST ({{ number_format((float) $invoice->cgst_rate, 2) }}%)</td><td>{{ number_format($cgst, 2) }}</td></tr>
                                @endif
                                @if ($sgst > 0)
                                    <tr><td>SGST ({{ number_format((float) $invoice->sgst_rate, 2) }}%)</td><td>{{ number_format($sgst, 2) }}</td></tr>
                                @endif
                                @if ($igst > 0)
                                    <tr><td>IGST ({{ number_format((float) $invoice->igst_rate, 2) }}%)</td><td>{{ number_format($igst, 2) }}</td></tr>
                                @endif
                                @if ($totalGst > 0)
                                    <tr><td>Total GST{{ $isRcm ? ' (RCM)' : '' }}</td><td>{{ number_format($totalGst, 2) }}</td></tr>
                                @endif
                            @endif
                            <tr class="grand"><td>Grand Total</td><td>{{ number_format($grandTotal, 2) }}</td></tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="footer-note"><strong>Amount in words:</strong> {{ ucfirst(getIndianCurrency($grandTotal)) }} Only</td>
                    <td class="signature-box">FOR {{ $company->name }}<br><br><br><br>Authorized Signature</td>
                </tr>
            </table>
        </div>
        <div class="section">
            <div class="footer-note" style="font-size: 7px; text-align: right;">Digitally Managed by TruckBill (www.truckbill.in)</div>
        </div>
    </div>

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
