<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Bilty Copy - {{ $trip->trip_number }}</title>
    <style>
        @page { margin: 6px 8px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 8.5px;
            margin: 0;
            padding: 0;
            color: #0f172a;
        }
        p { margin: 2px 0; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }
        .fs-5 { font-size: 11px; }
        .fs-6 { font-size: 8px; }
        .values { font-family: DejaVu Sans, monospace; }
        .text-end { text-align: right; }
        .lr-bilty-wrap {
            margin: 0 0 8px 0;
            padding: 0;
            page-break-inside: auto;
        }
        .lr-bilty-wrap:last-child {
            margin-bottom: 0;
        }
        .lr-sheet--a {
            border-radius: 10px;
            overflow: hidden;
            border: 2px solid #231f20;
            box-shadow: 0 0 0 1px rgba(35, 31, 32, 0.15);
        }
        .lr-sheet--a .lr-sheet__ribbon {
            font-weight: 800;
            font-size: 9px;
            letter-spacing: 0.6px;
            padding: 7px 12px;
            text-align: center;
        }
        .lr-sheet--a .lr-sheet__inner {
            padding: 6px 7px 8px;
        }
        .lr-sheet--a .lr-bilty-table {
            border: 1px solid #231f20;
        }
        .lr-sheet--a .lr-bilty-table td,
        .lr-sheet--a .lr-bilty-table th {
            border: 1px solid #231f20;
            padding: 3px 4px;
            vertical-align: top;
        }
        .company-logo {
            width: 72px;
            height: 46px;
            object-fit: contain;
            display: block;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
@foreach ($bilties as $index => $bilty)
    @if ($index > 0)
        <div class="page-break"></div>
    @endif
    <div class="lr-theme-a">
        <div class="lr-bilty-wrap lr-sheet lr-sheet--a">
            @include('trips.pdf.partials.bilty-block', [
                'bilty' => $bilty,
                'company' => $company,
                'company_line' => $company_line,
                'gst_number' => $gst_number,
                'pan_number' => $pan_number,
                'logo_path' => $logo_path ?? null,
                'is_rcm' => $is_rcm,
                'show_freight' => $show_freight,
                'copyLabel' => 'CONSIGNOR COPY',
            ])
        </div>
        <div class="lr-bilty-wrap lr-sheet lr-sheet--a" style="margin-top: 35px!important;">
            @include('trips.pdf.partials.bilty-block', [
                'bilty' => $bilty,
                'company' => $company,
                'company_line' => $company_line,
                'gst_number' => $gst_number,
                'pan_number' => $pan_number,
                'logo_path' => $logo_path ?? null,
                'is_rcm' => $is_rcm,
                'show_freight' => $show_freight,
                'copyLabel' => 'DRIVER COPY',
            ])
        </div>
    </div>
@endforeach
</body>
</html>
