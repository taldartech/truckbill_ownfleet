<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Memo Copy - {{ $memo_id }}</title>
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
            font-size: 9pt;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header h2 {
            margin: 0 0 5px 0;
            padding: 0;
            font-size: 16px;
        }
        .header p {
            margin: 2px 0;
            font-size: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            page-break-inside: avoid;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #000;
            padding: 3px;
            font-size: 8pt;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        th {
            background-color: #f2f2f2;
            color: #000;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .page-break {
            page-break-after: always;
        }
        .signature {
            margin-top: 30px;
        }
        .footer {
            margin-top: 10px;
            font-size: 8pt;
        }
        .no-border {
            border: none !important;
        }
        .company-name {
            font-size: 14px !important;
            font-weight: bold;
        }
        .company-address {
            font-size: 10px !important;
        }
    </style>
</head>
<body style="width: 100%;">
    @php
        // Calculate totals if not already set
        $company = auth()->user()->company;
        $customer = $lrs->first() ? $lrs->first()->customer : null;
    @endphp

    @include('reports.exports.memo-copy')
    <div style="margin-top: 4px; font-size: 7px; text-align: right;">Digitally Managed by TruckBill (www.truckbill.in)</div>

</body>
</html>
