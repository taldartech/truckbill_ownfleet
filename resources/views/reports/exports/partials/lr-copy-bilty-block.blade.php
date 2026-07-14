{{--
    One full Good Receipt (Bilty) block — same fields as resources/views/reports/exports/lr_copy.blade.php
    @var \App\Models\LorryReceipt $lr
    @var \App\Models\Company $company
    @var string $copyLabel  e.g. CONSIGNOR COPY | DRIVER COPY
--}}
@php
    $companyLine = ($company->address ?? '') . ', ' . optional($company->country)->name . ', ' . optional($company->state)->name . ', ' . optional($company->city)->name . ' - ' . ($company->pin_code ?? '') . ', Ph. ' . ($company->contact_number ?? '');
@endphp
<table class="lr-bilty-table" style="width:100%; border-collapse:collapse;">
    <tr>
        <td colspan="3" class="fs-6">AT OWNERS RISK</td>
        <td colspan="5"></td>
        <td colspan="4" class="text-center fw-bold">F-04(Q10-01)</td>
    </tr>
    <tr>
        <td colspan="12" class="text-center fw-bold">
            <p class="fs-5">{{ $company->name }}</p>
            <p class="">{{ $companyLine }}</p>
            <p class="">GST No: {{ $company->gst_number }}, PAN No: {{ $company->pan_number }}</p>
        </td>
    </tr>
    <tr>
        <td colspan="6" class="text-center fw-bold">GOOD RECEIPT (BILTY) FORM</td>
        <td colspan="6" class="text-center fw-bold">L.R. No.: <span class="values">{{ $lr->lr_number }}</span></td>
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
        <td colspan="3" class="fw-bold">Trk.Ownr PAN: <span class="values">{{ optional(optional($lr->truck)->transporter)->pan_number ?? '-' }}</span></td>
        <td colspan="3" class="fw-bold">Lr Dt: <span class="values">{{ \Carbon\Carbon::parse($lr->gr_date)->format('d.m.Y') }}</span></td>
        <td colspan="2" class="fw-bold">Truck No: <span class="values">{{ $lr->truck->truck_number }}</span></td>
        <td colspan="2" class="fw-bold">Shipment No: <span class="values">{{ $lr->invoice_number ?? '' }}</span></td>
        <td colspan="2" class="fw-bold">ShptDt: <span class="values">{{ \Carbon\Carbon::parse($lr->gr_date)->format('d.m.Y') }}</span></td>
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
            <p><span class="fw-bold">From:</span> <span class="values">{{ $lr->source_city . ', ' . $lr->source_state }}</span></p>
            <p><span class="fw-bold">To:</span> <span class="values">{{ $lr->destination_city . ', ' . $lr->destination_state }}</span></p>
            <p>Transhipment at if any:</p>
        </td>
    </tr>
    <tr>
        <td colspan="7" class="text-center fw-bold">Description(said to contain)/ Product Description</td>
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
            <p>{{ $copyLabel }}</p>
        </td>
        <td colspan="4" style="vertical-align: bottom">For {{ $company->name }}</td>
    </tr>
    <tr>
        <td colspan="12" class="fw-bold" style="font-size: 7px; text-align: right;">Digitally Managed by TruckBill (www.truckbill.in)</td>
    </tr>
</table>
