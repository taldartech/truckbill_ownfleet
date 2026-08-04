<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Lorry Receipt - {{ $trip->trip_number }}</title>
    <style>
        @page { margin: 14px 16px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }
        table { border-collapse: collapse; width: 100%; }
        .page-break { page-break-after: always; }
        .lr {
            border: 1px solid #1e3a5f;
            width: 100%;
        }
        .lr-header td {
            padding: 10px 12px;
            vertical-align: top;
            border: none;
        }
        .lr-company-name {
            font-size: 15px;
            font-weight: 700;
            margin: 0 0 3px 0;
            line-height: 1.2;
        }
        .lr-company-meta {
            font-size: 8.5px;
            line-height: 1.35;
            margin: 0;
        }
        .lr-title-cell {
            text-align: right;
            white-space: nowrap;
            width: 38%;
        }
        .lr-title {
            display: block;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.3px;
            margin: 0 0 6px 0;
        }
        .lr-title-meta {
            font-size: 10px;
            margin: 0;
            line-height: 1.45;
        }
        .party-head td {
            font-weight: 700;
            font-size: 10px;
            letter-spacing: 0.4px;
            padding: 6px 10px;
            border: 1px solid #000;
            width: 50%;
        }
        .party-body td {
            border: 1px solid #000;
            padding: 10px;
            font-size: 11px;
            font-weight: 600;
            vertical-align: top;
            min-height: 34px;
            width: 50%;
        }
        .section-head td {
            font-weight: 700;
            font-size: 10px;
            letter-spacing: 0.4px;
            padding: 6px 10px;
            border: 1px solid #000;
        }
        .grid-label {
            font-weight: 700;
            font-size: 9px;
            padding: 7px 8px;
            border: 1px solid #000;
            width: 22%;
            vertical-align: middle;
        }
        .grid-value {
            font-size: 10px;
            font-weight: 600;
            padding: 7px 8px;
            border: 1px solid #000;
            width: 28%;
            vertical-align: middle;
        }
        .freight-pending {
            font-weight: 800;
        }
        .sign-table td {
            width: 33.33%;
            vertical-align: top;
            padding: 0;
        }
        .sign-table {
            border: none;
        }
        .sign-head {
            margin: 0px 30px;
            border-top: 1px solid #000;
            font-weight: 700;
            font-size: 9.5px;
            padding: 7px 8px;
            text-align: center;
        }
        .sign-body {
            margin-top: 0;
            padding: 0px 25px;
            font-size: 9.5px;
            line-height: 1.9;
            min-height: 78px;
        }
        .footer-note {
            margin-top: 1px;
            text-align: right;
            font-size: 7px;
        }
    </style>
</head>
<body>
@foreach ($bilties as $index => $bilty)
    @if ($index > 0)
        <div class="page-break"></div>
    @endif

    @php
        $lrDate = \Carbon\Carbon::parse($bilty['lr_date']);
        $pickupDate = ! empty($bilty['pickup_date'])
            ? \Carbon\Carbon::parse($bilty['pickup_date'])
            : null;
        $distanceLabel = ((float) ($bilty['distance_km'] ?? 0)) > 0
            ? rtrim(rtrim(number_format((float) $bilty['distance_km'], 2), '0'), '.').'km approx'
            : '';
        $weightLabel = ((float) ($bilty['total_weight_ton'] ?? 0)) > 0
            ? number_format((float) $bilty['total_weight_ton'], 3).' MT'
            : '';
        $packagesLabel = ($bilty['no_of_packages'] ?? null) !== null
            ? (string) $bilty['no_of_packages']
            : '';
        $declaredLabel = ($bilty['declared_value'] ?? null) !== null
            ? ''.number_format((float) $bilty['declared_value'], 2)
            : '';
        $freightLabel = $show_freight
            ? '₹ '.number_format((float) $bilty['freight_amount'], 2)
            : '( TO BE BILLED )';
        $ewbValidity = ! empty($bilty['ewaybill_expiry'])
            ? \Carbon\Carbon::parse($bilty['ewaybill_expiry'])->format('d-m-Y')
            : '';
    @endphp

    <table class="lr">
        <tr class="lr-header">
            <td>
                <p class="lr-company-name">{{ $company?->name }}</p>
                @if ($company_line !== '')
                    <p class="lr-company-meta">{{ $company_line }}</p>
                @endif
                <p class="lr-company-meta">
                    GST: {{ $gst_number ?: '—' }}
                    @if ($pan_number)
                        | PAN: {{ $pan_number }}
                    @endif
                </p>
            </td>
            <td class="lr-title-cell">
                <span class="lr-title">LORRY RECEIPT (LR)</span>
                <p class="lr-title-meta">LR No: {{ $bilty['lr_number'] }}</p>
                <p class="lr-title-meta">Date: {{ $lrDate->format('d-m-Y') }}</p>
            </td>
        </tr>
    </table>

    <table class="lr" style="margin-top: 20px;">
        <tr class="party-head">
            <td>CONSIGNOR (FROM)</td>
            <td>CONSIGNEE (TO)</td>
        </tr>
        <tr class="party-body">
            <td>{{ $bilty['consignor'] }}</td>
            <td>{{ $bilty['consignee'] }}</td>
        </tr>
    </table>

    <table class="lr" style="margin-top: 20px;">
        <tr class="section-head">
            <td colspan="4">SHIPMENT DETAILS</td>
        </tr>
        <tr>
            <td class="grid-label">Vehicle No.</td>
            <td class="grid-value">{{ $bilty['truck_number'] }}</td>
            <td class="grid-label">Load Type</td>
            <td class="grid-value">{{ $bilty['load_type'] }}</td>
        </tr>
        <tr>
            <td class="grid-label">Origin</td>
            <td class="grid-value">{{ $bilty['origin'] }}</td>
            <td class="grid-label">Destination</td>
            <td class="grid-value">{{ $bilty['destination'] }}</td>
        </tr>
        <tr>
            <td class="grid-label">Distance (Approx.)</td>
            <td class="grid-value">{{ $distanceLabel }}</td>
            <td class="grid-label">Pickup Date</td>
            <td class="grid-value">{{ $pickupDate?->format('F d, Y') }}</td>
        </tr>
        <tr>
            <td class="grid-label">Goods Description</td>
            <td class="grid-value">{{ $bilty['goods_description'] }}</td>
            <td class="grid-label">Temperature Requirement</td>
            <td class="grid-value">{{ $bilty['temperature_requirement'] }}</td>
        </tr>
        <tr>
            <td class="grid-label">Total Weight</td>
            <td class="grid-value">{{ $weightLabel }}</td>
            <td class="grid-label">No. of Packages</td>
            <td class="grid-value">{{ $packagesLabel }}</td>
        </tr>
        <tr>
            <td class="grid-label">Declared Value</td>
            <td class="grid-value">{{ $declaredLabel }}</td>
            <td class="grid-label">Freight Charges</td>
            <td class="grid-value">
                @if ($show_freight)
                    {{ $freightLabel }}
                @else
                    <span class="freight-pending">{{ $freightLabel }}</span>
                @endif
            </td>
        </tr>
    </table>

    <table class="lr" style="margin-top: 20px;">
        <tr class="section-head">
            <td colspan="4">E-WAY BILL &amp; COMPLIANCE</td>
        </tr>
        <tr>
            <td class="grid-label">E-Way Bill No.</td>
            <td class="grid-value">{{ $bilty['ewaybill_no'] ?: '' }}</td>
            <td class="grid-label">E-Way Bill Date</td>
            <td class="grid-value"></td>
        </tr>
        <tr>
            <td class="grid-label">GST Number</td>
            <td class="grid-value">{{ $bilty['customer_gst_number'] ?: '' }}</td>
            <td class="grid-label">EWB Validity</td>
            <td class="grid-value">{{ $ewbValidity }}</td>
        </tr>
    </table>

    <table class="lr sign-table" style="margin-top: 100px;">
        <tr>
            <td>
                <div class="sign-head">Consignor Signature</div>
                <div class="sign-body">
                    Company<br>
                    Date
                </div>
            </td>
            <td>
                <div class="sign-head">{{ $company?->name }}</div>
                <div class="sign-body">
                    Date
                </div>
            </td>
            <td>
                <div class="sign-head">Consignee Signature</div>
                <div class="sign-body">
                    Company<br>
                    Date
                </div>
            </td>
        </tr>
    </table>
    <div class="footer-note">Digitally Managed by TruckBill (www.truckbill.in)</div>
@endforeach
</body>
</html>
