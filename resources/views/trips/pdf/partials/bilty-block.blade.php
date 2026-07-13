<div class="lr-sheet__ribbon">
    Lorry Receipt / Good Receipt / Bilty Copy -
    <span class="fw-bold">{{ $bilty['lr_number'] }}</span>
</div>
<div class="lr-sheet__inner">
<table class="lr-bilty-table" style="width:100%; border-collapse:collapse;">
    <tr>
        <td colspan="12" class="text-center fw-bold">
            <p class="fs-5">{{ $company?->name }}</p>
            <p class="">{{ $company_line }}</p>
            <p class="">GST No: {{ $gst_number ?: '—' }}, PAN No: {{ $pan_number ?: '—' }}</p>
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
            consignment Policy No. Date Amount Risk.
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
        <td colspan="3">
            <p class="fw-bold">LR No.:</p>
            <p class="values fw-bold">{{ $bilty['lr_number'] }}</p>
        </td>
        <td colspan="3">
            <p class="fw-bold">Trip No:</p>
            <p class="values">{{ $bilty['trip_number'] }}</p>
        </td>
        <td colspan="2">
            <p class="fw-bold">LR Date: </p>
            <p class="values">{{ \Carbon\Carbon::parse($bilty['lr_date'])->format('d.m.Y') }}</p>
        </td>
        <td colspan="2">
            <p class="fw-bold">Truck Number: </p>
            <p class="values fw-bold">{{ $bilty['truck_number'] }}</p>
        </td>
        <td colspan="2">
            <p class="fw-bold">Invoice No: </p>
            <p class="values">{{ $bilty['invoice_number'] ?? '' }}</p>
        </td>
    </tr>
    <tr>
        <td colspan="8">
            <p>
                <span class="fw-bold">Customer's Name:</span>
                <span class="values">{{ $bilty['customer_name'] }}</span>
                <span class="fw-bold">GST NO:</span>
                <span class="values">{{ $bilty['customer_gst_number'] ?: '—' }}</span>
            </p>
            @if (! empty($bilty['driver_name']))
                <p>
                    <span class="fw-bold">Driver:</span>
                    <span class="values">{{ $bilty['driver_name'] }}</span>
                </p>
            @endif
        </td>
        <td colspan="4" class="fw-bold">
            <p>
                <span class="fw-bold">From:</span>
                <span class="values">{{ $bilty['from_location'] }}</span>
            </p>
            <p>
                <span class="fw-bold">To:</span>
                <span class="values">{{ $bilty['to_location'] }}</span>
            </p>
        </td>
    </tr>
    <tr class="text-center">
        <td colspan="7" class="text-center fw-bold">Description(said to contain)/ Product Description</td>
        <td colspan="3" class="text-center fw-bold">Route</td>
        <td colspan="2" class="text-center fw-bold">Weight(MT)</td>
    </tr>
    @foreach ($bilty['routes'] as $route)
        <tr class="text-center">
            <td colspan="7" class="values">{{ $route['goods_name'] ?: '—' }}</td>
            <td colspan="3" class="values">{{ $route['from_location'] }} → {{ $route['to_location'] }}</td>
            <td colspan="2" class="values">{{ number_format((float) $route['weight_ton'], 3) }}</td>
        </tr>
    @endforeach
    <tr class="text-center">
        <td colspan="7" class="values">Total</td>
        <td colspan="3" class="values">{{ (int) $bilty['route_count'] }} route(s)</td>
        <td colspan="2" class="values">{{ number_format((float) $bilty['total_weight_ton'], 3) }}</td>
    </tr>
    <tr>
        <td colspan="9">
            &nbsp;
        </td>
        <td colspan="3">
            <span class="fw-bold">TotalFreight:</span>
            <span class="values">
                @if ($show_freight)
                    {{ number_format((float) $bilty['freight_amount'], 2) }}
                @endif
            </span>
        </td>
    </tr>
    <tr>
        <td colspan="8">
            <p>AT OWNERS RISK</p>
            <p>Declaration: We hereby declare that we have not availed input credit on capital goods and
                for providing services.</p>
            @if ($is_rcm)
                <p>Under RCM (Reverse Charge Mechanism) GST will be paid by service recipient only.</p>
            @endif
            <p>{{ $copyLabel }}</p>
        </td>
        <td colspan="4" style="vertical-align: bottom; text-align: right;">For {{ $company?->name }}</td>
    </tr>
    <tr>
        <td colspan="12" class="fw-bold" style="font-size: 7px; text-align: right;">Digitally Managed by OwnFleet</td>
    </tr>
</table>
</div>
