{{--
    Freight / Lorry Hire challan — Form B (detailed English layout).
    Same data contract as resources/views/reports/exports/voucher-receipt.blade.php: $lrs, $company.
    Fields not stored in the app are shown as em dash or blank.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Challan — {{ $company->template_name ?? $company->name }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; margin: 6px; color: #111; }
        .ch-wrap { border: 1px solid #000; padding: 6px; }
        .ch-title { text-align: center; font-size: 14px; font-weight: bold; letter-spacing: 2px; margin: 0 0 4px; }
        .ch-sub { text-align: center; font-size: 9px; margin: 0 0 6px; }
        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.grid td, table.grid th {
            border: 1px solid #000;
            padding: 2px 3px;
            vertical-align: top;
        }
        table.grid th { background: #f3f4f6; font-size: 7px; text-align: left; }
        .muted { color: #444; font-size: 7px; }
        .main-table { width: 100%; border-collapse: collapse; font-size: 8px; }
        .main-table th, .main-table td { border: 1px solid #000; padding: 3px; text-align: center; }
        .main-table th { background: #eee; }
        .summary-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .summary-table td { border: 1px solid #000; padding: 3px; vertical-align: top; }
        .sig { margin-top: 8px; font-size: 7px; }
        ul.chk { margin: 4px 0 0 12px; padding: 0; font-size: 7px; }
        ul.chk li { margin-bottom: 1px; }
        .ch-head-table { width: 100%; border-collapse: collapse; margin-bottom: 6px; table-layout: fixed; }
        .ch-head-table td { border: none; vertical-align: middle; padding: 2px 4px; }
        .ch-head-logo-col { width: 58px !important; max-width: 58px !important; }
    </style>
</head>
<body>
@php
    $company = $company ?? auth()->user()?->company;
    $company?->loadMissing(['city', 'state', 'country']);
    $firstLr = $lrs->first();
    $firstFb = $firstLr?->freightBookings?->last();
    $totalAmount = 0;
    $detention = 0;
    $deviation = 0;
    $misc = 0;
    $shortage = 0;
    $damage = 0;
    $tds = 0;
    $isLinked = $lrs->count() > 1;
    $paymentNames = \App\Models\PaymentMode::pluck('name')->toArray();
    $payments = array_fill_keys($paymentNames, 0);
    foreach ($lrs as $lr) {
        $fb = $lr->freightBookings->last();
        if (!$fb) {
            continue;
        }
        $totalAmount += (float) ($fb->tr_amount ?? 0);
        $detention += (float) ($fb->detention ?? 0);
        $deviation += (float) ($fb->deviation ?? 0);
        $misc += (float) ($fb->misc_expense ?? 0);
        $shortage += (float) ($lr->shortage_amount ?? 0);
        $damage += (float) ($lr->damage_amount ?? 0);
        $tds += (float) ($fb->tds_amount ?? 0);
        if ($isLinked) {
            $vouchers = \App\Models\VoucherLinked::where('freight_booking_id', $fb->id)->get();
        } else {
            $vouchers = \App\Models\VoucherPayment::where('freight_booking_id', $fb->id)->get();
        }
        foreach ($vouchers as $voucher) {
            $payments[$voucher->payment_mode] = ($payments[$voucher->payment_mode] ?? 0) + (float) $voucher->amount;
        }
    }
    $expenses = $detention + $deviation;
    $totalWithExtras = $totalAmount + $expenses;
    $totalDeductions = $shortage + $damage + $misc;
    $grandTotal = $totalWithExtras - $totalDeductions;
    $balance = $grandTotal - array_sum($payments) - $tds;
    $challanLogoSrc = $company->logoFilesystemPathForPdf() ?: $company->logoPublicUrl();
@endphp

<div class="ch-wrap">
    <table class="ch-head-table">
        <tr>
            <td class="ch-head-logo-col" style="text-align: left; vertical-align: middle;">
                @if (! empty($challanLogoSrc))
                    @include('reports.exports.partials.pdf-company-logo-slot', [
                        'logoSrc' => $challanLogoSrc,
                        'companyName' => $company->name,
                        'slotWidthPx' => 52,
                        'slotHeightPx' => 22,
                    ])
                @endif
            </td>
            <td style="width: auto; text-align: center;">
                <p class="ch-title" style="margin: 0 0 2px;">CHALLAN</p>
                <p class="ch-sub" style="margin: 0;"><strong>{{ $company->template_name ?? $company->name }}</strong></p>
            </td>
            <td style="width: 22%; text-align: right; font-size: 7px;">
                <span class="muted">Mobile</span><br>
                {{ $company->contact_number ?? '—' }}
            </td>
        </tr>
    </table>

    <table class="grid" style="margin-bottom: 6px;">
        <tr>
            <td style="width: 50%;">
                <span class="muted">Registered office / address (voucher template)</span><br>
                {{ $company->template_address ?? $company->address ?? '—' }}
            </td>
            <td style="width: 25%;">
                <span class="muted">Challan No.</span><br>
                <strong>{{ $firstLr->lr_number ?? '—' }}</strong>
            </td>
            <td style="width: 25%;">
                <span class="muted">GST / PAN</span><br>
                {{ $company->gst_number ?? '—' }}
                @if($company->pan_number)
                    &nbsp;/ {{ $company->pan_number }}
                @endif
            </td>
        </tr>
    </table>

    <table class="grid">
        <tr>
            <th>Date</th>
            <th>Vehicle No.</th>
            <th>Vehicle type</th>
            <th>Model</th>
        </tr>
        <tr>
            <td>{{ $firstLr->gr_date ? \Carbon\Carbon::parse($firstLr->gr_date)->format('d-m-Y') : '—' }}</td>
            <td>{{ optional($firstLr->truck)->truck_number ?? '—' }}</td>
            <td>{{ optional($firstLr->truck)->truck_type ?? '—' }}</td>
            <td>—</td>
        </tr>
        <tr>
            <th>Loaded from</th>
            <th>Destination</th>
            <th>Distance (KM)</th>
            <th>EWay bill / Validity</th>
        </tr>
        <tr>
            <td>{{ $firstLr->source_city ?? '—' }}</td>
            <td>{{ $firstLr->destination_city ?? '—' }}</td>
            <td>—</td>
            <td>
                @if($firstFb && ($firstFb->eway_bill_number || $firstFb->eway_bill_expiry))
                    {{ $firstFb->eway_bill_number ?? '—' }}
                    @if($firstFb->eway_bill_expiry)
                        / {{ \Carbon\Carbon::parse($firstFb->eway_bill_expiry)->format('d-m-Y') }}
                    @endif
                @else
                    —
                @endif
            </td>
        </tr>
        <tr>
            <th colspan="2">Driver name, mobile, address</th>
            <th colspan="2">Vehicle owner / transporter (PAN)</th>
        </tr>
        <tr>
            <td colspan="2">
                {{ optional(optional($firstLr->truck)->driver)->name ?? '—' }}
                @if(optional(optional($firstLr->truck)->driver)->mobile)
                    &nbsp;|&nbsp; {{ $firstLr->truck->driver->mobile }}
                @endif
                <br>
                <span class="muted">{{ optional(optional($firstLr->truck)->driver)->address ?? '' }}</span>
            </td>
            <td colspan="2">
                {{ optional($firstLr->transporter)->name ?? '—' }}
                @if(optional($firstLr->transporter)->pan_number)
                    &nbsp;PAN: {{ optional($firstLr->transporter)->pan_number }}
                @endif
                <br>
                <span class="muted">{{ optional($firstLr->transporter)->address ?? '' }}</span>
            </td>
        </tr>
    </table>

    <p class="muted" style="margin: 4px 0 2px;">Goods (from Good Receipt / Bilty)</p>
    <table class="main-table">
        <thead>
            <tr>
                <th>Sr.</th>
                <th>Bkg. / Station</th>
                <th>Bilty No.</th>
                <th>Pkg (Nos.)</th>
                <th>Said to contain</th>
                <th>Wt (MT)</th>
                <th>Destination</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lrs as $idx => $lr)
                @php $fb = $lr->freightBookings->last(); @endphp
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>{{ $lr->unloading_station ?? optional($lr->consignee)->name ?? '—' }}</td>
                    <td>{{ $lr->lr_number }}</td>
                    <td>{{ $lr->no_of_units ?? '—' }}</td>
                    <td style="text-align: left;">{{ $lr->good_type ?? '—' }}</td>
                    <td>{{ $lr->weight ?? '—' }}</td>
                    <td>{{ $lr->destination_city ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="summary-table">
        <tr>
            <td style="width: 55%;">
                <strong>Payable summary</strong>
                <table class="grid" style="margin-top: 4px;">
                    <tr>
                        <th>Freight / hire</th>
                        <th>Detn. / Dev.</th>
                        <th>Short / Damage / Misc</th>
                        <th>TDS</th>
                    </tr>
                    <tr>
                        <td>{{ number_format($totalAmount, 2) }}</td>
                        <td>{{ number_format($expenses, 2) }}</td>
                        <td>{{ number_format($totalDeductions, 2) }}</td>
                        <td>{{ number_format($tds, 2) }}</td>
                    </tr>
                </table>
                <p style="font-size: 7px; margin-top: 4px;">
                    <strong>Net amount:</strong> {{ number_format($grandTotal, 2) }}
                    &nbsp;|&nbsp; <strong>Balance:</strong> {{ number_format($balance, 2) }}
                </p>
                @if($company->template_jurisdictional_boundary)
                    <p class="muted" style="margin-top: 4px;">{{ $company->template_jurisdictional_boundary }}</p>
                @endif
            </td>
            <td style="width: 45%;">
                <strong>Payments</strong>
                <table class="grid" style="margin-top: 4px;">
                    @foreach ($payments as $mode => $amount)
                        @if((float) $amount > 0)
                            <tr><td>{{ $mode }}</td><td>{{ number_format($amount, 2) }}</td></tr>
                        @endif
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    <div class="sig">
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%; border-top: 1px solid #000; padding-top: 4px;">Owner / Driver</td>
                <td style="width: 50%; border-top: 1px solid #000; padding-top: 4px; text-align: right;">For {{ $company->template_name ?? $company->name }}</td>
            </tr>
        </table>
        <p class="muted" style="margin-top: 6px;">
            Checklist (manual): engine/chassis impression, licence &amp; documents, insurance, permit — not tracked in software; use physical form where required.
        </p>
        <p class="muted" style="margin-top: 6px; font-size: 7px; text-align: right;">Digitally Managed by TruckBill (www.truckbill.in)</p>
    </div>
</div>
</body>
</html>
