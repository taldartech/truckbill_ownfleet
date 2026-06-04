<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Party Bill (Compact) -
        {{
            $invoice->invoice_number
                ? $invoice->invoice_number . ($invoice->manual_invoice_number ? ' (' . $invoice->manual_invoice_number . ')' : '')
                : ($invoice->manual_invoice_number ?? $invoice->id)
        }}
    </title>
    <style>
        @page { margin: 10px 8px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8.6pt;
            margin: 0;
            color: #111827;
        }
        .compact-shell {
            border: 1.5px solid #2FB36F;
            padding: 6px;
            background: #ffffff;
            border-radius: 6px;
            box-shadow: inset 0 0 0 1px #E8F5EE;
        }
        .compact-top {
            border: 0;
            background: linear-gradient(90deg, #2FB36F, #23965C);
            padding: 7px 8px;
            margin-bottom: 6px;
            border-radius: 4px;
        }
        .compact-note {
            color: #ffffff;
            font-size: 9.2px;
            font-weight: 600;
            letter-spacing: 0.25px;
        }
        .compact-company {
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            margin-top: 2px;
        }
        .compact-sub {
            color: #eaf8ff;
            font-size: 7.5px;
            margin-top: 1px;
        }
        .compact-content table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }
        .compact-content th,
        .compact-content td {
            border: 1px solid #d1d5db !important;
            padding: 2px 2px !important;
            font-size: 7.7pt !important;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .compact-content th {
            background: #E9F7F0 !important;
            color: #23965C;
            font-weight: 700;
        }
    </style>
</head>
<body>
    @php
        $company = $invoice->company ?? auth()->user()->company;
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
    @endphp

    <div class="compact-shell">
        <div class="compact-content">
            @include('reports.exports.party-bill', ['partyBillHeaderCompact' => true])
        </div>
    </div>
    <div style="margin-top: 4px; font-size: 7px; text-align: right;">Powered by TruckBill (www.truckbill.in)</div>

    <script type="text/php">
        if (isset($pdf)) {
            $text = "{PAGE_NUM}/{PAGE_COUNT}";
            $size = 8;
            $font = $fontMetrics->getFont("Arial");
            $x = 550;
            $y = $pdf->get_height() - 12;
            $pdf->page_text($x, $y, $text, $font, $size);
        }
    </script>
</body>
</html>

