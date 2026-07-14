<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice - INV-{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="192x192"  href="{{ asset('favicon/android-icon-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon/favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('favicon/site.webmanifest') }}">
    <!-- End Favicon -->
    <style>
        @page { margin: 20px 15px; }
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0 0 5px 0;
            padding: 0;
            font-size: 24px;
            font-weight: bold;
        }
        .header .company-logo {
            margin-bottom: 8px;
        }
        .header .company-logo img {
            max-height: 70px;
            max-width: 220px;
            width: auto;
        }
        .header h2 {
            margin: 0 0 5px 0;
            padding: 0;
            font-size: 18px;
        }
        .header p {
            margin: 2px 0;
            font-size: 10pt;
        }
        .invoice-info {
            margin-bottom: 20px;
        }
        .invoice-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-info td {
            padding: 5px;
            vertical-align: top;
        }
        .invoice-info .label {
            font-weight: bold;
            width: 150px;
        }
        .company-details {
            margin-bottom: 20px;
        }
        .company-details table {
            width: 100%;
            border-collapse: collapse;
        }
        .company-details td {
            padding: 5px;
            vertical-align: top;
        }
        .company-details .label {
            font-weight: bold;
            width: 150px;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        table.items th, table.items td {
            border: 1px solid #000;
            padding: 8px;
            font-size: 10pt;
        }
        table.items th {
            background-color: #f2f2f2;
            font-weight: bold;
            text-align: left;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .text-left {
            text-align: left;
        }
        .total-section {
            margin-top: 20px;
        }
        .total-section table {
            width: 100%;
            border-collapse: collapse;
            margin-left: auto;
            margin-right: 0;
            max-width: 400px;
        }
        .total-section td {
            padding: 8px;
            border: 1px solid #000;
        }
        .total-section .label {
            font-weight: bold;
            text-align: right;
            width: 60%;
        }
        .total-section .amount {
            text-align: right;
            width: 40%;
        }
        .total-section .total-row {
            background-color: #f2f2f2;
            font-weight: bold;
            font-size: 12pt;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #000;
            font-size: 9pt;
        }
        .footer p {
            margin: 3px 0;
        }
        .payment-info {
            margin-top: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }
        .payment-info table {
            width: 100%;
            border-collapse: collapse;
        }
        .payment-info td {
            padding: 5px;
        }
        .payment-info .label {
            font-weight: bold;
            width: 200px;
        }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($payment->company->company_logo))
            <div class="company-logo">
                <img src="{{ public_path('storage/' . $payment->company->company_logo) }}" alt="{{ $payment->company->name }} Logo">
            </div>
        @endif
        <h1>TAX INVOICE</h1>
        <h2>{{ $payment->company->name ?? 'Company Name' }}</h2>
        <p>Invoice No: INV-{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</p>
        <p>Date: {{ \Carbon\Carbon::parse($payment->created_at)->format('d M, Y') }}</p>
    </div>

    <div class="company-details">
        <table>
            <tr>
                <td class="label">Bill To:</td>
                <td>
                    <strong>{{ $payment->company->name }}</strong><br>
                    @if($payment->company->address)
                        {{ $payment->company->address }}<br>
                    @endif
                    @if($payment->company->city)
                        {{ $payment->company->city->name ?? '' }},
                    @endif
                    @if($payment->company->state)
                        {{ $payment->company->state->name ?? '' }}<br>
                    @endif
                    @if($payment->company->pin_code)
                        PIN: {{ $payment->company->pin_code }}<br>
                    @endif
                    @if($payment->company->gst_number)
                        GSTIN: {{ $payment->company->gst_number }}<br>
                    @endif
                    @if($payment->company->contact_number)
                        Phone: {{ $payment->company->contact_number }}<br>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">Sr.</th>
                <th style="width: 45%;">Description</th>
                <th style="width: 15%;" class="text-center">Quantity</th>
                <th style="width: 15%;" class="text-right">Rate</th>
                <th style="width: 20%;" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @php
                $sr = 1;
            @endphp

            @if($payment->payment_type == 'renewal')
                @if($payment->truck_count && $payment->truck_cost > 0)
                    <tr>
                        <td class="text-center">{{ $sr++ }}</td>
                        <td>
                            <strong>Truck Subscription</strong><br>
                            <small>Number of Trucks: {{ $payment->truck_count }} | Tenure: {{ $payment->tenure }} Year(s)</small>
                        </td>
                        <td class="text-center">{{ $payment->truck_count }}</td>
                        <td class="text-right">Rs. {{ number_format($payment->truck_cost / ($payment->truck_count * $payment->tenure), 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($payment->truck_cost, 2) }}</td>
                    </tr>
                @endif

                @if($payment->lr_credits && $payment->lr_credits_cost > 0)
                    <tr>
                        <td class="text-center">{{ $sr++ }}</td>
                        <td>
                            <strong>LR Credits</strong><br>
                            <small>{{ $payment->lr_credits }} Credits</small>
                        </td>
                        <td class="text-center">{{ $payment->lr_credits }}</td>
                        <td class="text-right">Rs. {{ number_format($payment->lr_credits_cost / $payment->lr_credits, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($payment->lr_credits_cost, 2) }}</td>
                    </tr>
                @endif

                @if($payment->annual_maintenance_cost > 0)
                    <tr>
                        <td class="text-center">{{ $sr++ }}</td>
                        <td>
                            <strong>Annual Maintenance Fee</strong><br>
                            <small>Tenure: {{ $payment->tenure }} Year(s)</small>
                        </td>
                        <td class="text-center">{{ $payment->tenure }}</td>
                        <td class="text-right">Rs. {{ number_format($payment->annual_maintenance_cost / $payment->tenure, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($payment->annual_maintenance_cost, 2) }}</td>
                    </tr>
                @endif
            @else
                @if($payment->addon_truck_count && $payment->truck_cost > 0)
                    <tr>
                        <td class="text-center">{{ $sr++ }}</td>
                        <td>
                            <strong>Additional Truck Subscription</strong><br>
                            <small>Additional Trucks: {{ $payment->addon_truck_count }}</small>
                        </td>
                        <td class="text-center">{{ $payment->addon_truck_count }}</td>
                        <td class="text-right">Rs. {{ number_format($payment->truck_cost / $payment->addon_truck_count, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($payment->truck_cost, 2) }}</td>
                    </tr>
                @endif

                @if($payment->addon_lr_credits && $payment->lr_credits_cost > 0)
                    <tr>
                        <td class="text-center">{{ $sr++ }}</td>
                        <td>
                            <strong>Additional LR Credits</strong><br>
                            <small>{{ $payment->addon_lr_credits }} Credits</small>
                        </td>
                        <td class="text-center">{{ $payment->addon_lr_credits }}</td>
                        <td class="text-right">Rs. {{ number_format($payment->lr_credits_cost / $payment->addon_lr_credits, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($payment->lr_credits_cost, 2) }}</td>
                    </tr>
                @endif
            @endif
        </tbody>
    </table>

    <div class="total-section">
        <table>
            <tr>
                <td class="label">Subtotal:</td>
                <td class="amount">Rs. {{ number_format($payment->subtotal, 2) }}</td>
            </tr>
            @if($payment->promoCode && $payment->promo_discount > 0)
                <tr>
                    <td class="label">
                        Discount
                        @if($payment->promoCode->discount_type == 'percentage')
                            ({{ $payment->promoCode->discount_value }}%)
                        @endif
                        @if($payment->promoCode->code)
                            [{{ $payment->promoCode->code }}]:
                        @endif
                    </td>
                    <td class="amount">- Rs. {{ number_format($payment->promo_discount, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Subtotal after Discount:</td>
                    <td class="amount">Rs. {{ number_format($payment->subtotal - $payment->promo_discount, 2) }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">GST ({{ config('payments.service_gst_percentage') }}%):</td>
                <td class="amount">Rs. {{ number_format($payment->gst_amount, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td class="label">Total Amount:</td>
                <td class="amount">Rs. {{ number_format($payment->total_amount, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="payment-info">
        <table>
            <tr>
                <td class="label">Payment Method:</td>
                <td>Razorpay Online Payment</td>
            </tr>
            <tr>
                <td class="label">Transaction ID:</td>
                <td>{{ $payment->razorpay_payment_id ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">Order ID:</td>
                <td>{{ $payment->razorpay_order_id }}</td>
            </tr>
            <tr>
                <td class="label">Payment Status:</td>
                <td><strong>{{ strtoupper($payment->status) }}</strong></td>
            </tr>
            <tr>
                <td class="label">Payment Date:</td>
                <td>{{ \Carbon\Carbon::parse($payment->created_at)->format('d M, Y H:i:s') }}</td>
            </tr>
            @if($payment->upcomingPlan)
                <tr>
                    <td class="label">Plan Start Date:</td>
                    <td>{{ \Carbon\Carbon::parse($payment->upcomingPlan->plan_start_date)->format('d M, Y') }}</td>
                </tr>
                <tr>
                    <td class="label">Plan End Date:</td>
                    <td>{{ \Carbon\Carbon::parse($payment->upcomingPlan->plan_end_date)->format('d M, Y') }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div class="footer">
        <p><strong>Terms & Conditions:</strong></p>
        <p>1. This is a computer-generated invoice and does not require a signature.</p>
        <p>2. All disputes are subject to the jurisdiction of the courts.</p>
        <p style="margin-top: 20px; text-align: center;">
            <strong>Thank you for your business!</strong>
        </p>
        <p style="margin-top: 16px; font-size: 7px; text-align: right;">Digitally Managed by TruckBill (www.truckbill.in)</p>
    </div>
</body>
</html>

