<!DOCTYPE html>
<html lang="hi">

<head>
    <meta charset="UTF-8">
    <title>भाड़ा-चालान - {{ $company->template_name ?? $company->name ?? '' }}</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="192x192"  href="{{ asset('favicon/android-icon-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon/favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('favicon/site.webmanifest') }}">
    <!-- End Favicon -->
    <style>
        body {
            font-family: 'NotoSansDevanagari', Arial, sans-serif;
            font-size: 10px;
            margin: 10px;
        }

        .container {
            width: 100%;
            border: 1px solid #000;
            padding: 8px;
            box-sizing: border-box;
        }

        .header {
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
            margin-bottom: 5px;
        }

        .header h2 {
            margin: 0;
            font-weight: bold;
        }

        /* ===== Top Line Section ===== */
        .top-line-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .top-line-table td {
            border: none;
            vertical-align: top;
            padding: 3px;
        }

        .details {
            /* border-bottom: 1px solid #000; */
            padding-bottom: 5px;
            margin-bottom: 5px;
            width: 100%;
            line-height: 1.5;
            font-size: 12px;
        }

        table.main-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        table.main-table th,
        table.main-table td {
            border: 1px solid #000;
            /* padding: 4px; */
            text-align: center;
            vertical-align: middle;
        }

        /* ===== Summary Section ===== */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #000;
            margin-top: 5px;
        }

        .summary-table td {
            border: none;
            vertical-align: top;
            padding: 3px;
        }

        .summary-left {
            font-size: 11px;
            width: 70%;
        }

        .summary-right {
            border: 1px solid #000;
            width: 30%;
        }

        .summary-right table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-right td {
            border: 1px solid #000;
            padding: 3px;
            font-size: 11px;
        }

        .footer {
            margin-top: 5px;
            font-size: 11px;
            border-top: 1px solid #000;
            padding-top: 5px;
        }

        .dotted-underline {
            border-bottom: 1px dotted #000;
            display: inline-block;
            padding: 0 5px;
            min-width: 100px;
        }

        /* Logo: compact slot for voucher header */
    </style>
</head>

<body>
    @php
        $voucherLogoSrc = $company->logoFilesystemPathForPdf() ?: $company->logoPublicUrl();
        $templateName = $company->template_name ?? $company->name ?? '';
        $templateAddress = $company->template_address ?? $company->address ?? '';
    @endphp
    <div class="container">

        <!-- Top Line (Replaced Flex with Table) -->
        <table class="top-line-table">
            <tr>
                <td style="width:26%; text-align:left;">
                  @if (!empty($company->template_jurisdictional_boundary))
                    {{ $company->template_jurisdictional_boundary }}<br>
                  @endif
                    <span style="font-size: 18px"><b>(भाड़ा-चालान)</b></span>
                </td>
                <td style="width:48%; text-align:center;">
                    @if (!empty($voucherLogoSrc))
                        <div style="margin: 0 auto 3px;">
                            @include('reports.exports.partials.pdf-company-logo-slot', [
                                'logoSrc' => $voucherLogoSrc,
                                'companyName' => $company->name,
                                'slotWidthPx' => 64,
                                'slotHeightPx' => 28,
                            ])
                        </div>
                    @endif
                    @if (!empty($company->template_header))
                    <b>{{ $company->template_header }}<br>
                    @endif
                        <span style="font-size: 27px">{{ $templateName }}</span>
                    </b><br>
                    {{ $templateAddress }}
                </td>
                <td style="width:26%; text-align:right;">
                    <b>Tel.:</b> {{ $company->contact_number ?? '—' }}<br>
                    <br>
                    <br>
                    चालान नं. ............................
                </td>
            </tr>
        </table>
        @php
            $firstLr = $lrs->first();
            $totalAmount = 0;
            $detention = 0;
            $deviation = 0;
            $misc = 0;
            $shortage = 0;
            $damage = 0;
            $tds = 0;

            $isLinked = count($lrs) > 1 ? true : false;
            $paymentNames = \App\Models\PaymentMode::pluck('name')->toArray();
            $payments = array_fill_keys($paymentNames, 0);
        @endphp

        <!-- Details Section (Replaced Flex with Table) -->
        <div class="details">
            ट्रक नं.
            @if (!empty($firstLr->truck->truck_number))
                <span class="dotted-underline">{{ $firstLr->truck->truck_number }}</span>
            @else
                ...........................
            @endif
            ड्राईवर श्री
            @if (!empty(optional($firstLr->truck->driver)->name))
                <span class="dotted-underline">{{ $firstLr->truck->driver->name }}</span>
            @else
                ...........................
            @endif
            फोन नं.
            @if (!empty(optional($firstLr->truck->driver)->mobile))
                <span class="dotted-underline">{{ $firstLr->truck->driver->mobile }}</span>
            @else
                ...........................
            @endif
            संदर्भ द्वारा
            @if (!empty(optional($firstLr->reference)->name))
            <span
                class="dotted-underline">{{ $firstLr->reference->name }}</span>
        @else
            ...........................................
        @endif

            निवासी
            @if (!empty(optional($firstLr->truck->driver)->address))
                <span class="dotted-underline">{{ $firstLr->truck->driver->address }}</span>
            @else
                ................................................
            @endif
            लाइसेंस नं.
            @if (!empty(optional($firstLr->truck->driver)->license_number))
                <span class="dotted-underline">{{ $firstLr->truck->driver->license_number }}</span>
            @else
                ...........................
            @endif
            ट्रक मालिक का नाम
            @if (!empty(optional($firstLr->transporter)->name))
                <span class="dotted-underline">{{ optional($firstLr->transporter)->name }}</span>
            @else
                ...........................
            @endif
            पता
            @if (
                !empty(optional($firstLr->transporter)->address) ||
                    !empty(optional(optional($firstLr->transporter)->city)->name))
                <span class="dotted-underline">
                    {{ optional($firstLr->transporter)->address ?? '' }},
                    {{ optional(optional($firstLr->transporter)->city)->name ?? '' }}
                </span>
            @else
                ...........................
            @endif
            कहाँ से
            @if (!empty($firstLr->source_city))
                <span class="dotted-underline">{{ $firstLr->source_city }}</span>
            @else
                ...........................
            @endif
            कहाँ को
            @if (!empty($firstLr->destination_city))
                <span class="dotted-underline">{{ $firstLr->destination_city }}</span>
            @else
                ...........................
            @endif
            दिनांक
            @if (!empty(optional(optional($firstLr->freightBookings)->last())->created_at))
                <span
                    class="dotted-underline">{{ $firstLr->freightBookings->last()->created_at->format('d-m-Y') }}</span>
            @else
                ...........................
            @endif

        </div>

        <!-- Main Table -->
        <table class="main-table">
            <thead>
                <tr>
                    <th style="width: 17%;">भेजने वाले का नाम</th>
                    <th style="width: 17%;">पाने वाले का नाम</th>
                    <th style="width: 13%;">बिल्टी नं.</th>
                    <th style="width: 14%;">विवरण</th>
                    <th style="width: 8%;">कहे</th>
                    <th style="width: 8%;">वजन</th>
                    <th style="width: 10%;">रेट</th>
                    <th style="width: 12%;">कुल रकम</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lrs as $lr)
                    @php
                        $freightBooking = $lr->freightBookings->last();
                        $totalAmount += $freightBooking->tr_amount ?? 0;
                        $detention += $freightBooking->detention ?? 0;
                        $deviation += $freightBooking->deviation ?? 0;
                        $misc += $freightBooking->misc_expense ?? 0;
                        $shortage += $lr->shortage_amount ?? 0;
                        $damage += $lr->damage_amount ?? 0;
                        $tds += $freightBooking->tds_amount ?? 0;
                        if ($isLinked) {
                            $vouchers = \App\Models\VoucherLinked::where(
                                'freight_booking_id',
                                $freightBooking->id,
                            )->get();
                        } else {
                            $vouchers = \App\Models\VoucherPayment::where(
                                'freight_booking_id',
                                $freightBooking->id,
                            )->get();
                        }
                        foreach ($vouchers as $voucher) {
                            $payments[$voucher->payment_mode] += $voucher->amount;
                        }
                    @endphp
                    <tr>
                        <td>{{ $lr->consignor->name }}</td>
                        <td>{{ $lr->consignee->name }}</td>
                        <td>{{ $lr->lr_number }}</td>
                        <td>{{ $lr->good_type }}</td>
                        <td>{{ $lr->no_of_units }}</td>
                        <td>{{ $lr->weight }}</td>
                        <td>{{ $freightBooking->tr_fr_rate }}</td>
                        <td>{{ $freightBooking->tr_amount }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Summary Section -->
        <table class="summary-table">
            <tr>
                <td class="summary-left" style="font-size: 12px;">
                    <p><b>R.C. PAN / TDS FORM:</b> जमा □ &nbsp;&nbsp;&nbsp; बाकी □</p>
                    <br>
                    {!! $company->template_note !!}
                </td>
                <td class="summary-right">
                    <table>
                        <tr>
                            @php
                                $expenses = $detention + $deviation;
                            @endphp
                            <td>Detention/Deviation</td>
                            <td>{{ number_format($expenses, 2) }}</td>
                        </tr>
                        <tr>
                            @php
                                $totalWithExtras = $totalAmount + $expenses;
                            @endphp
                            <td>Total</td>
                            <td>{{ number_format($totalWithExtras, 2) }}</td>
                        </tr>
                        <tr>
                            @php
                                $totalDeductions = $shortage + $damage + $misc;
                            @endphp
                            <td>Short/Other</td>
                            <td>{{ number_format($totalDeductions, 2) }}</td>
                        </tr>
                        <tr>
                            @php
                                $grandTotal = $totalWithExtras - $totalDeductions;
                            @endphp
                            <td>Amount</td>
                            <td>{{ number_format($grandTotal, 2) }}</td>
                        </tr>
                        @foreach ($payments as $mode => $amount)
                            <tr>
                                <td>{{ $mode }}</td>
                                <td>{{ number_format($amount, 2) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td>TDS</td>
                            <td>{{ number_format($tds, 2) }}</td>
                        </tr>
                        <tr>
                            <td><b>Balance</b></td>
                            <td>
                                <b>{{ number_format($grandTotal - array_sum($payments) - $tds, 2) }}</b>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

    </div>
    <div style="margin-top: 6px; font-size: 7px; text-align: right;">Digitally Managed by TruckBill (www.truckbill.in)</div>
</body>

</html>
