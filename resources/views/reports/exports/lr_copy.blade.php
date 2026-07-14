<!DOCTYPE html>
<html lang="en">

<head>
    <title>LR COPY</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="192x192"  href="{{ asset('favicon/android-icon-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon/favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('favicon/site.webmanifest') }}">
    <!-- End Favicon -->
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }

        td,
        th {
            border: 1px solid #000;
            padding: 4px;
            vertical-align: top;
        }

        .text-center {
            text-align: center;
        }

        p {
            margin: 2px 0;
        }

        .fw-bold {
            font-weight: bold;
        }

        .fs-5 {
            font-size: 1rem;
        }

        .fs-6 {
            font-size: 0.75rem;
        }

        .values {
            font-family: 'Hubballi', monospace, sans-serif;
        }
    </style>
</head>

<body>
    @php
        if ($lr->linked_lr_id) {
            $lrs = \App\Models\LorryReceipt::where('linked_lr_id', $lr->linked_lr_id)
            ->where('company_id', $company->id)->get();
        } else {
            $lrs = [$lr];
        }
        $company = $company ?? auth()->user()?->company;
        $company?->loadMissing(['city', 'state', 'country']);
    @endphp
    @foreach ($lrs as $index => $lr)
        @if ($index > 0)
            <div style="page-break-after: always;"></div>
        @endif
        <div class="container">
            <table style="width:100%">
                <tr>
                    <td colspan="3" class="fs-6">AT OWNERS RISK</td>
                    <td colspan="5"></td>
                    <td colspan="4" class="text-center fw-bold">F-04(Q10-01)</td>
                </tr>
                <tr>
                    <td colspan="12" class="text-center fw-bold">
                        <p class="fs-5">{{ $company->name }}</p>
                        <p class="">
                            {{ $company->address . ', ' . optional($company->country)->name . ', ' . optional($company->state)->name . ', ' . optional($company->city)->name . ' - ' . $company->pin_code ?? '' . ', Ph. ' . $company->contact_number }}
                        </p>
                        <p class="">GST No: {{ $company->gst_number }}, PAN No: {{ $company->pan_number }}</p>
                    </td>
                </tr>
                <tr>
                    <td colspan="6" class="text-center fw-bold">GOOD RECEIPT (BILTY) FORM</td>
                    <td colspan="6" class="text-center fw-bold">L.R. No.: <span
                            class="values">{{ $lr->lr_number }}</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="3" class="text-center fw-bold">CAUTION</td>
                    <td colspan="3" class="text-center fw-bold">INSURANCE</td>
                    <td colspan="6" class="text-center fw-bold">NOTICE</td>
                </tr>
                <tr>
                    <td colspan="3" class="values">
                        This consignment will not be detained, delivered, re-quoted, or re-booked without consignee
                        Bank's written permission.
                    </td>
                    <td colspan="3" class="values">
                        The Consignor has stated that* He has not insured the consignment or* He has insured the
                        consignment Policy No. Date Amount Risk.Question is that:
                    </td>
                    <td colspan="6" class="values">
                        The consignment covered by this set of Special Good Receipt (Bilty) form shall be stored under control
                        of the Transport Operator and shall be delivered to the consignee Party, whose name as mentioned
                        in the Good Receipt (Bilty). It will under no circumstances be delivered to anyone without the written
                        authority from the Consignee party or its order recorded on the Consignee copy or on separate
                        Letter of Authority.
                    </td>
                </tr>
                <tr>
                    <td colspan="3" class="fw-bold">Trk.Ownr PAN: <span
                            class="values">{{ optional(optional($lr->truck)->transporter)->pan_number ?? '-' }}</span></td>
                    <td colspan="3" class="fw-bold">Lr Dt: <span
                            class="values">{{ \Carbon\Carbon::parse($lr->gr_date)->format('d.m.Y') }}</span></td>
                    <td colspan="2" class="fw-bold">Truck No: <span
                            class="values">{{ $lr->truck->truck_number }}</span></td>
                    <td colspan="2" class="fw-bold">Shipment No: <span
                            class="values">{{ $lr->invoice_number ?? '' }}</span></td>
                    <td colspan="2" class="fw-bold">ShptDt: <span
                            class="values">{{ \Carbon\Carbon::parse($lr->gr_date)->format('d.m.Y') }}</span></td>
                </tr>
                <tr>
                    <td colspan="8">
                        <p>
                            <span class="fw-bold">Consignor's Name:</span>
                            <span class="values">{{ $lr->consignor->name }}</span>
                        </p>
                        <p>
                            <span class="fw-bold">TIN:</span>
                            <span class="values"></span>
                            <span class="fw-bold">GST NO:</span>
                            <span class="values">{{ $lr->consignor->gst_number }}</span>
                        </p>
                        <p>
                            <span class="fw-bold">Consignee's Name:</span>
                            <span class="values">{{ $lr->consignee->name }}</span>
                            <span class="fw-bold">GST NO</span>
                            <span class="values">{{ $lr->consignee->gst_number }}</span>
                        </p>
                    </td>
                    <td colspan="4">
                        <p><span class="fw-bold">From:</span> <span
                                class="values">{{ $lr->source_city . ', ' . $lr->source_state }}</span></p>
                        <p><span class="fw-bold">To:</span> <span
                                class="values">{{ $lr->destination_city . ', ' . $lr->destination_state }}</span></p>
                        <p>Transhipment at if any:</p>
                    </td>
                </tr>
                <tr>
                    <td colspan="7" class="text-center fw-bold">Description(said to contain)/ Product Description
                    </td>
                    <td colspan="3" class="text-center fw-bold">No Of Bags</td>
                    <td colspan="2" class="text-center fw-bold">Weight(MT)</td>
                </tr>
                <tr>
                    <td colspan="7" class="values">{{ $lr->good_type }}</td>
                    <td colspan="3" class="values text-end">{{ $lr->no_of_units }}</td>
                    <td colspan="2" class="values text-end">{{ $lr->weight }}</td>
                </tr>
                <tr>
                    <td colspan="7" class="values">Total</td>
                    <td colspan="3" class="values text-end">{{ $lr->no_of_units }}</td>
                    <td colspan="2" class="values text-end">{{ $lr->weight }}</td>
                </tr>
                <tr>
                    <td colspan="3" class="fw-bold">FREIGHT Amt:
                        <span class="values">
                            @if (request()->has('show_freight') && request()->get('show_freight') == 1)
                                {{ number_format($lr->amount, 2) }}
                            @endif
                        </span>
                    </td>
                    <td colspan="3" class="fw-bold">Del.Chrgs.: <span class="values"></span></td>
                    <td colspan="3" class="fw-bold">TotalFreight:
                        <span class="values">
                            @if (request()->has('show_freight') && request()->get('show_freight') == 1)
                                {{ number_format($lr->amount, 2) }}
                            @endif
                        </span>
                    </td>
                    <td colspan="3" class="fw-bold">Freight/MT: <span class="values"></span></td>
                </tr>
                <tr>
                    <td colspan="8">
                        <p>Declaration: We hereby declare that we have not availed input credit on capital goods and
                            forproviding services.</p>
                        @if ($company->gst_scheme == 'reverse charge')
                            <p>Under RCM (Reverse Charge Mechanism) GST will be paid by service recipient only.</p>
                        @endif
                        <p>CONSIGNOR COPY</p>
                    </td>
                    <td colspan="4" style="vertical-align: bottom">For {{ $company?->name }}</td>
                </tr>
                <tr>
                    <td colspan="12" class="fw-bold" style="font-size: 7px; text-align: right;">Digitally Managed by TruckBill (www.truckbill.in)</td>
                </tr>
            </table>
        </div>
        <div class="container" style="margin-top: 20px;">
            <table style="width:100%">
                <tr>
                    <td colspan="3" class="fs-6">AT OWNERS RISK</td>
                    <td colspan="5"></td>
                    <td colspan="4" class="text-center fw-bold">F-04(Q10-01)</td>
                </tr>
                <tr>
                    <td colspan="12" class="text-center fw-bold">
                        <p class="fs-5">{{ $company->name }}</p>
                        <p class="">
                            {{ $company->address . ', ' . optional($company->country)->name . ', ' . optional($company->state)->name . ', ' . optional($company->city)->name . ' - ' . $company->pin_code ?? '' . ', Ph. ' . $company->contact_number }}
                        </p>
                        <p class="">GST No: {{ $company->gst_number }}, PAN No: {{ $company->pan_number }}</p>
                    </td>
                </tr>
                <tr>
                    <td colspan="6" class="text-center fw-bold">GOOD RECEIPT (BILTY) FORM</td>
                    <td colspan="6" class="text-center fw-bold">L.R. No.: <span
                            class="values">{{ $lr->lr_number }}</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="3" class="text-center fw-bold">CAUTION</td>
                    <td colspan="3" class="text-center fw-bold">INSURANCE</td>
                    <td colspan="6" class="text-center fw-bold">NOTICE</td>
                </tr>
                <tr>
                    <td colspan="3" class="values">
                        This consignment will not be detained, delivered, re-quoted, or re-booked without consignee
                        Bank's written permission.
                    </td>
                    <td colspan="3" class="values">
                        The Consignor has stated that* He has not insured the consignment or* He has insured the
                        consignment Policy No. Date Amount Risk.Question is that:
                    </td>
                    <td colspan="6" class="values">
                        The consignment covered by this set of Special Good Receipt (Bilty) form shall be stored under control
                        of the Transport Operator and shall be delivered to the consignee Party, whose name as mentioned
                        in the Good Receipt (Bilty). It will under no circumstances be delivered to anyone without the written
                        authority from the Consignee party or its order recorded on the Consignee copy or on separate
                        Letter of Authority.
                    </td>
                </tr>
                <tr>
                    <td colspan="3" class="fw-bold">Trk.Ownr PAN: <span
                            class="values">{{ optional(optional($lr->truck)->transporter)->pan_number ?? '-' }}</span></td>
                    <td colspan="3" class="fw-bold">Lr Dt: <span
                            class="values">{{ \Carbon\Carbon::parse($lr->gr_date)->format('d.m.Y') }}</span></td>
                    <td colspan="2" class="fw-bold">Truck No: <span
                            class="values">{{ $lr->truck->truck_number }}</span></td>
                    <td colspan="2" class="fw-bold">Shipment No: <span
                            class="values">{{ $lr->invoice_number ?? '' }}</span></td>
                    <td colspan="2" class="fw-bold">ShptDt: <span
                            class="values">{{ \Carbon\Carbon::parse($lr->gr_date)->format('d.m.Y') }}</span></td>
                </tr>
                <tr>
                    <td colspan="8">
                        <p>
                            <span class="fw-bold">Consignor's Name:</span>
                            <span class="values">{{ $lr->consignor->name }}</span>
                        </p>
                        <p>
                            <span class="fw-bold">TIN:</span>
                            <span class="values"></span>
                            <span class="fw-bold">GST NO:</span>
                            <span class="values">{{ $lr->consignor->gst_number }}</span>
                        </p>
                        <p>
                            <span class="fw-bold">Consignee's Name:</span>
                            <span class="values">{{ $lr->consignee->name }}</span>
                            <span class="fw-bold">GST NO</span>
                            <span class="values">{{ $lr->consignee->gst_number }}</span>
                        </p>
                    </td>
                    <td colspan="4">
                        <p><span class="fw-bold">From:</span> <span
                                class="values">{{ $lr->source_city . ', ' . $lr->source_state }}</span></p>
                        <p><span class="fw-bold">To:</span> <span
                                class="values">{{ $lr->destination_city . ', ' . $lr->destination_state }}</span></p>
                        <p>Transhipment at if any:</p>
                    </td>
                </tr>
                <tr>
                    <td colspan="7" class="text-center fw-bold">Description(said to contain)/ Product Description
                    </td>
                    <td colspan="3" class="text-center fw-bold">No Of Bags</td>
                    <td colspan="2" class="text-center fw-bold">Weight(MT)</td>
                </tr>
                <tr>
                    <td colspan="7" class="values">{{ $lr->good_type }}</td>
                    <td colspan="3" class="values text-end">{{ $lr->no_of_units }}</td>
                    <td colspan="2" class="values text-end">{{ $lr->weight }}</td>
                </tr>
                <tr>
                    <td colspan="7" class="values">Total</td>
                    <td colspan="3" class="values text-end">{{ $lr->no_of_units }}</td>
                    <td colspan="2" class="values text-end">{{ $lr->weight }}</td>
                </tr>
                <tr>
                    <td colspan="3" class="fw-bold">FREIGHT Amt:
                        <span class="values">
                            @if (request()->has('show_freight') && request()->get('show_freight') == 1)
                                {{ number_format($lr->amount, 2) }}
                            @endif
                        </span>
                    </td>
                    <td colspan="3" class="fw-bold">Del.Chrgs.: <span class="values"></span></td>
                    <td colspan="3" class="fw-bold">TotalFreight:
                        <span class="values">
                            @if (request()->has('show_freight') && request()->get('show_freight') == 1)
                                {{ number_format($lr->amount, 2) }}
                            @endif
                        </span>
                    </td>
                    <td colspan="3" class="fw-bold">Freight/MT: <span class="values"></span></td>
                </tr>
                <tr>
                    <td colspan="8">
                        <p>Declaration: We hereby declare that we have not availed input credit on capital goods and
                            forproviding services.</p>
                        @if ($company->gst_scheme == 'reverse charge')
                            <p>Under RCM (Reverse Charge Mechanism) GST will be paid by service recipient only.</p>
                        @endif
                        <p>DRIVER COPY</p>
                    </td>
                    <td colspan="4" style="vertical-align: bottom">For {{ $company?->name }}</td>
                </tr>
                <tr>
                    <td colspan="12" class="fw-bold" style="font-size: 7px; text-align: right;">Digitally Managed by TruckBill (www.truckbill.in)</td>
                </tr>
            </table>
        </div>
    @endforeach
</body>

</html>
