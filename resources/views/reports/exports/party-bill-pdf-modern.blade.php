<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Party Bill (Modern) -
        {{
            $invoice->invoice_number
                ? $invoice->invoice_number . ($invoice->manual_invoice_number ? ' (' . $invoice->manual_invoice_number . ')' : '')
                : ($invoice->manual_invoice_number ?? $invoice->id)
        }}
    </title>
    <style>
        @page { margin: 14px 12px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9pt;
            margin: 0;
            padding: 0;
            color: #0f172a;
            background: #ffffff;
        }
        .invoice-card {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            overflow: hidden;
        }
        .top-band {
            background-color: #2FB36F;
            background: #2FB36F;
            color: #ffffff;
            padding: 10px 14px;
            border-bottom: 2px solid #23965C;
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
        .section {
            padding: 10px 12px;
        }
        .chip-title {
            background: #E9F7F0;
            color: #23965C;
            border: 1px solid #cceedd;
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
            border: 1px solid #d1d5db;
            padding: 5px 4px;
            font-size: 8.3pt;
            word-wrap: break-word;
            overflow-wrap: break-word;
            vertical-align: top;
        }
        th {
            background: #E9F7F0;
            color: #23965C;
            font-weight: 700;
        }
        .meta-table td {
            background: #f8fafc;
        }
        .meta-label {
            width: 32%;
            font-weight: 700;
            color: #374151;
        }
        .summary-table td {
            background: #f8fafc;
        }
        .summary-table .grand td {
            background: #ecfdf5;
            font-weight: 700;
            color: #14532d;
        }
        .footer-note {
            font-size: 9px;
            color: #374151;
            line-height: 1.4;
        }
        .signature-box {
            text-align: center;
            font-weight: 700;
        }
        .muted {
            color: #6b7280;
        }
        .invoice-logo-cell {
            width: 76px;
            vertical-align: middle;
            padding-right: 10px;
        }
        .invoice-logo-box {
            width: 68px;
            height: 68px;
            background: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 6px;
            text-align: center;
            padding: 4px;
            line-height: 0;
        }
        .invoice-logo-box img {
            max-width: 60px;
            max-height: 60px;
            width: auto;
            height: auto;
            object-fit: contain;
            display: inline-block;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    @php
        $company = $company ?? ($invoice->company ?? auth()->user()->company);
        $consignee = $invoice->consignee;
        $lrs = $lrs ?? collect();
        $customDetails = $customDetails ?? collect();
        $cgst = (float) ($cgst ?? 0);
        $sgst = (float) ($sgst ?? 0);
        $igst = (float) ($igst ?? 0);
        $baseAmount = (float) ($totalAmount ?? 0);
        $grandTotal = ($invoice->invoice_type ?? '') === 'Reverse'
            ? (float) ($invoice->net_amount ?? $invoice->base_amount ?? $baseAmount)
            : (float) ($invoice->total_amount ?? ($baseAmount + $cgst + $sgst + $igst));
        $billNumber = $invoice->invoice_number
            ? $invoice->invoice_number . ($invoice->manual_invoice_number ? ' (' . $invoice->manual_invoice_number . ')' : '')
            : ($invoice->manual_invoice_number ?? $invoice->id);
        $totalGst = $cgst + $sgst + $igst;
        $usePdfLogoPath = ($partyBillInvoiceUsePdfLogoPath ?? false) === true;
        $invoiceCompanyLogoSrc = $usePdfLogoPath
            ? $company->logoFilesystemPathForPdf()
            : $company->logoPublicUrl();
    @endphp

    <div class="invoice-card">
        <div class="top-band">
            <table style="width: 100%; border-collapse: collapse; border: none;">
                <tr>
                    @if ($invoiceCompanyLogoSrc)
                        <td class="invoice-logo-cell" style="border: none;">
                            <div class="invoice-logo-box">
                                <img src="{{ $invoiceCompanyLogoSrc }}" alt="{{ $company->name }}">
                            </div>
                        </td>
                    @endif
                    <td style="border: none; vertical-align: top;">
                        <p class="invoice-title">INVOICE</p>
                        <div class="company-name">{{ $company->name }}</div>
                        <div class="company-meta">
                            {{ $company->address }},
                            {{ optional($company->city)->name }},
                            {{ optional($company->state)->name }} - {{ $company->pin_code }}
                        </div>
                        <div class="company-meta">GSTIN: {{ $company->gst_number }} | PAN: {{ $company->pan_number }}</div>
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
                        <div style="font-weight:700; font-size:10px;">{{ $consignee->name }}</div>
                        <div>{{ optional($consignee->city)->name }}, {{ optional($consignee->state)->name }}</div>
                        <div>GSTIN: {{ $consignee->gst_number }}</div>
                        <div>PAN: {{ $consignee->pan_number }}</div>
                    </td>
                    <td width="48%">
                        <table>
                            <tr>
                                <td class="meta-label">Invoice No.</td>
                                <td>{{ $billNumber }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Invoice Date</td>
                                <td>{{ $invoice->generated_date }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">Reverse Charge</td>
                                <td>{{ $invoice->invoice_type === 'Forward' ? 'No' : 'Yes' }}</td>
                            </tr>
                            <tr>
                                <td class="meta-label">SAC Code</td>
                                <td>996511</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <div class="chip-title">Transportation Bill</div>
            @if ($invoice->custom_invoice)
                <table>
                    <tr>
                        <th width="6%">#</th>
                        <th width="13%">GR No</th>
                        <th width="14%">Truck</th>
                        <th width="16%">Destination</th>
                        <th width="13%">Invoice No</th>
                        <th width="12%">Date</th>
                        <th width="10%">Weight</th>
                        @if ($invoice->custom_invoice === 'rate_difference')
                            <th width="8%">Portal</th>
                            <th width="8%">Diff</th>
                        @else
                            <th width="8%">Shortage</th>
                            <th width="8%">Damage</th>
                        @endif
                        <th width="10%">Amount</th>
                    </tr>
                    @foreach ($customDetails as $index => $row)
                        @php $lr = $row->lorryReceipt; @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $lr ? $lr->lr_number : '-' }}</td>
                            <td>{{ $lr && $lr->truck ? $lr->truck->truck_number : '-' }}</td>
                            <td>{{ $lr ? ($lr->destination_city ?? '-') : '-' }}</td>
                            <td>{{ $lr ? ($lr->invoice_number ?? '-') : '-' }}</td>
                            <td>{{ $lr && $lr->gr_date ? \Carbon\Carbon::parse($lr->gr_date)->format('d-m-Y') : '-' }}</td>
                            <td>{{ $lr ? ($lr->weight ?? '-') : '-' }}</td>
                            @if ($invoice->custom_invoice === 'rate_difference')
                                <td>{{ $row->portal_rate !== null ? number_format($row->portal_rate, 2) : '-' }}</td>
                                <td>{{ $row->diff !== null ? number_format($row->diff, 2) : '-' }}</td>
                            @else
                                <td>{{ $row->shortage_qty !== null ? $row->shortage_qty : '-' }}</td>
                                <td>{{ $row->damage_qty !== null ? $row->damage_qty : '-' }}</td>
                            @endif
                            <td>{{ number_format((float) ($row->amount ?? 0), 2) }}</td>
                        </tr>
                    @endforeach
                </table>
            @else
                <table>
                    <tr>
                        <th width="6%">#</th>
                        <th width="12%">GR No</th>
                        <th width="12%">Truck No</th>
                        <th width="12%">Destination</th>
                        <th width="11%">Invoice No</th>
                        <th width="10%">Date</th>
                        <th width="9%">Load Wt.</th>
                        <th width="9%">Unload Wt.</th>
                        <th width="7%">Bags</th>
                        <th width="8%">Fr Rate</th>
                        <th width="7%">Shortage</th>
                        <th width="7%">Damage</th>
                        <th width="10%">Amount</th>
                    </tr>
                    @foreach ($lrs as $index => $lr)
                        @php
                            $shortageAmount = (float) ($lr->shortage_amount ?? 0);
                            $damageAmount = (float) ($lr->damage_amount ?? 0);
                            $lineAmount = (float) (($lr->actual_amount ?? 0) - ($shortageAmount + $damageAmount));
                        @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $lr->lr_number }}</td>
                            <td>{{ optional($lr->truck)->truck_number ?? '-' }}</td>
                            <td>{{ $lr->destination_city }}</td>
                            <td>{{ $lr->invoice_number }}</td>
                            <td>{{ \Carbon\Carbon::parse($lr->gr_date)->format('d-m-Y') }}</td>
                            <td>{{ $lr->weight }}</td>
                            <td>{{ number_format($lr->unloading_weight ?? $lr->weight, 3) }}</td>
                            <td>{{ $lr->no_of_units }}</td>
                            <td>{{ number_format((float) ($lr->fr_rate ?? 0), 2) }}</td>
                            <td>{{ number_format($shortageAmount, 2) }}</td>
                            <td>{{ number_format($damageAmount, 2) }}</td>
                            <td>{{ number_format($lineAmount, 2) }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif

            <table>
                <tr>
                    <td width="62%">
                        <div class="chip-title" style="margin-bottom:6px;">Payment Details</div>
                        <div class="footer-note">
                            <strong>Bank:</strong> {{ $company->bank ?? '' }}<br>
                            <strong>A/C:</strong> {{ $company->account_number ?? '' }}<br>
                            <strong>IFSC:</strong> {{ $company->ifsc ?? '' }}<br>
                            @if ($invoice->invoice_type == 'Reverse')
                            <strong>Note:</strong> GST Liability of Debtor:
                            {{ $consignee->name }} under Reverse Charge Mechanism of GST Act, 2017.
                            @endif
                        </div>
                    </td>
                    <td width="38%">
                        <table class="summary-table">
                            <tr><td>Base Amount</td><td>{{ number_format($baseAmount, 2) }}</td></tr>
                            @if ($cgst > 0)
                                <tr><td>CGST</td><td>{{ number_format($cgst, 2) }}</td></tr>
                            @endif
                            @if ($sgst > 0)
                                <tr><td>SGST</td><td>{{ number_format($sgst, 2) }}</td></tr>
                            @endif
                            @if ($igst > 0)
                                <tr><td>IGST</td><td>{{ number_format($igst, 2) }}</td></tr>
                            @endif
                            @if ($totalGst > 0)
                                <tr><td>Total GST</td><td>{{ number_format($totalGst, 2) }}</td></tr>
                            @endif
                            <tr class="grand"><td>Grand Total</td><td>{{ number_format($grandTotal, 2) }}</td></tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="footer-note"><strong>Amount in words:</strong> {{ ucfirst(getIndianCurrency($grandTotal)) }} Only</td>
                    <td class="signature-box">FOR {{ $company->name }}<br><br>Authorized Signature</td>
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

