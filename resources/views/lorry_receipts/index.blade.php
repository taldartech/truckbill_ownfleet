@extends('layouts.app')

@push('styles')
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/searchpanes/2.2.0/css/searchPanes.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/select/1.7.0/css/select.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.75);
            --glass-border: rgba(255, 255, 255, 0.3);
            --primary-brand: var(--brand-primary);
            --primary-brand-dark: var(--brand-primary-dark);
            --success-brand: var(--brand-success);
            --warning-brand: var(--brand-warning);
            --danger-brand: var(--brand-danger);
            --slate-600: var(--brand-text-muted);
            --slate-800: var(--brand-text-primary);
        }

        .table td, .table th {
            padding: 6px 8px !important;
            font-size: 11.5px !important;
            vertical-align: middle;
            border-color: rgba(0,0,0,0.05) !important;
        }

        /* Horizontal Scrolling & Layout */
        .table-responsive {
            overflow-x: auto !important;
            scrollbar-width: thin;
        }

        #lorryReceiptsTable {
            min-width: 1200px; /* Force overflow on smaller screens */
        }

        #lorryReceiptsTable th,
        #lorryReceiptsTable td {
            white-space: nowrap !important;
        }

        /* Sticky Header Fix for Horizontal Scroll */
        #lorryReceiptsTable thead th {
            position: sticky;
            top: 0;
            background: var(--brand-table-header-bg) !important;
            z-index: 20;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .container {
            max-width: 100% !important;
            padding-left: 20px !important;
            padding-right: 20px !important;
        }

        /* Glassmorphism Cards */
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        }

        .card-header {
            background: transparent !important;
            border-bottom: 1px solid rgba(0,0,0,0.05) !important;
            padding: 12px 20px !important;
        }

        .card-title {
            color: var(--slate-800);
            font-weight: 700 !important;
            letter-spacing: -0.025em;
        }

        /* Status Dots */
        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px !important;
            border-radius: 50px !important;
            font-weight: 600 !important;
            text-transform: uppercase;
            font-size: 9px !important;
            letter-spacing: 0.05em;
        }

        .status-delivered { background: var(--brand-active-bg); color: var(--brand-primary-dark); }
        .status-delivered .status-dot { background: var(--brand-success); }
        .status-pending { background: #FFF7ED; color: #9A3412; } /* Derived from Amber */
        .status-pending .status-dot { background: var(--brand-warning); }

        /* Action Buttons */
        .btn-action {
            width: 28px;
            height: 28px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            background: white;
            color: var(--slate-600);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            opacity: 0.9;
        }

        .btn-action i {
            color: white !important;
            font-size: 14px;
        }

        /* Semantic Button Colors */
        .btn-action-warning { background: var(--warning-brand) !important; }
        .btn-action-success { background: var(--success-brand) !important; }
        .btn-action-info    { background: var(--brand-info) !important; }
        .btn-action-primary { background: var(--primary-brand) !important; }
        .btn-action-danger  { background: var(--danger-brand) !important; }
        .btn-action-muted   { background: var(--brand-text-muted) !important; }

        .lr-number-link {
            color: var(--brand-primary) !important;
            font-weight: 700;
            text-decoration: none !important;
        }

        .lr-number-link:hover {
            color: var(--brand-primary-dark) !important;
            text-decoration: underline !important;
        }

        .route-visual {
            color: #94a3b8;
            font-size: 10px;
            margin: 0 4px;
        }

        .city-label {
            font-size: 10.5px;
            color: var(--slate-600);
            font-weight: 500;
        }

        /* Header Specific Adjustments */
        .header-pill-btn {
            border-radius: 50px !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            padding: 4px 12px !important;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
        }

        .header-pill-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
        }

        .filter-action-btn {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .filter-action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .input-group-custom {
            background: white;
            border-radius: 10px;
            padding: 2px 8px;
            border: 1px solid rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }

        .input-group-custom span {
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--slate-600);
            margin-right: 8px;
        }

        .input-group-custom input {
            border: none !important;
            font-size: 12px !important;
            padding: 2px 0 !important;
            background: transparent !important;
            outline: none !important;
            box-shadow: none !important;
        }

        /* dataTables_wrapper .row first & last child */
        .dataTables_wrapper .row:first-child,
        .dataTables_wrapper .row:last-child {
            padding: 0 12px !important;
        }
    </style>
@endpush

@section('content')
    <div class="container-fluid py-2">
        <div class="d-flex justify-content-between align-items-center mb-2 px-3">
            <div>
                @if (session('success'))
                    <span class="badge bg-success animate__animated animate__fadeIn">{{ session('success') }}</span>
                @endif
                @if (session('error'))
                    <span class="badge bg-danger animate__animated animate__fadeIn">{{ session('error') }}</span>
                @endif
            </div>
        </div>

        <div class="glass-card mb-3 animate__animated animate__fadeInDown">
            <div class="card-header py-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <h4 class="card-title mb-0" style="white-space: nowrap;">Good Receipts (Bilty)</h4>
                        <div class="d-flex gap-2">
                            <a href="{{ route('lorry_receipts.create') }}" class="header-pill-btn btn btn-primary border-0 shadow-sm" style="background: var(--brand-primary);">
                                <i class="bi bi-plus-circle me-1"></i> Create
                            </a>
                            <a href="{{ route('lorry_receipts.createLinked') }}" class="header-pill-btn btn btn-edit shadow-sm">
                                <i class="bi bi-link-45deg me-1"></i> Linked
                            </a>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <div class="input-group-custom">
                                <span>From</span>
                                <input type="date" id="fromDate">
                            </div>
                            <div class="input-group-custom">
                                <span>To</span>
                                <input type="date" id="toDate">
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="filter-action-btn" id="filterBtn" style="background: var(--brand-primary); color: white;" title="Filter" data-bs-toggle="tooltip">
                                <i class="bi bi-funnel-fill"></i>
                            </button>
                            <button type="button" class="filter-action-btn" id="resetDateFilter" style="background: var(--brand-text-primary); color: white;" title="Reset" data-bs-toggle="tooltip">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <div class="glass-card animate__animated animate__fadeInUp">
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: calc(100vh - 250px); overflow-y: auto;">
                <table id="lorryReceiptsTable" class="table table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>GR Date</th>
                            <th>LR Details</th>
                            <th>Customer</th>
                            <th>Consignor</th>
                            <th>Consignee</th>
                            <th>Status</th>
                            <th>Truck</th>
                            <th>Weight</th>
                            <th>Rate</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
    <!-- LR Copy Modal -->
    <div class="modal fade" id="lrCopyModal" tabindex="-1" aria-labelledby="lrCopyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="lrCopyModalLabel">
                        <i class="bi bi-file-earmark-text"></i> LR Copy
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="mb-2 small text-muted">
                        PDF layout (A / B / C) uses the Good Receipt LR copy template saved in Company settings.
                    </p>
                    <p class="mb-4">Select the type of LR copy you want to generate:</p>
                    <div class="d-flex gap-3 justify-content-center">
                        <button type="button" class="btn btn-primary btn-lg" onclick="generateLrCopy(true)">
                            <i class="bi bi-truck"></i> With Freight
                        </button>
                        <button type="button" class="btn btn-primary btn-lg" onclick="generateLrCopy(false)">
                            <i class="bi bi-file-text"></i> Without Freight
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Good Receipt (Bilty) Details Modal -->
    <div class="modal fade" id="lrDetailsModal" tabindex="-1" aria-labelledby="lrDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary">
                    <h5 class="modal-title" id="lrDetailsModalLabel">
                        <i class="bi bi-file-text me-2"></i>Good Receipt (Bilty) Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" id="lrDetailsContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading good receipt (bilty) details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i> Close
                    </button>
                    @if (Auth::user()->canActAsAdmin())
                        <a href="#" class="btn btn-primary" id="editLorryReceiptBtn">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Modal styles */
        .lr-details-container {
            padding: 1.5rem;
        }

        .lr-details-section {
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
            background-color: var(--brand-bg);
            border: 1px solid var(--brand-border);
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .lr-details-section h5 {
            background-color: #f1f8ff;
            padding: 0.75rem 1.25rem;
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--brand-primary);
            border-bottom: 1px solid #e9ecef;
        }

        .lr-details-content {
            padding: 1.25rem;
        }

        .lr-details-row {
            display: flex;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px dashed #e9ecef;
        }

        .lr-details-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .lr-details-label {
            width: 200px;
            font-weight: 500;
            color: #6c757d;
            flex-shrink: 0;
        }

        .lr-details-value {
            flex: 1;
            color: #212529;
            word-break: break-word;
        }

        .badge {
            font-weight: 500;
            padding: 0.35em 0.65em;
        }

        .badge.bg-warning {
            color: #000;
        }

        /* Floating buttons */
        .floating-buttons button {
            display: block;
            width: 100%;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .floating-buttons button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .selected-lr-list {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 1rem;
        }

        .selected-lr-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }

        .selected-lr-list li:last-child {
            border-bottom: none;
        }

        #voucherPaymentBody .mb-3 {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 6px;
        }

        .lr-details-container {
            padding: 20px;
        }

        .lr-details-section {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid var(--brand-border);
            border-radius: 5px;
        }

        .lr-details-section h5 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #495057;
        }

        .lr-details-row {
            display: flex;
            margin-bottom: 10px;
        }

        .lr-details-label {
            width: 150px;
            font-weight: 600;
            color: #6c757d;
        }

        .lr-details-value {
            flex: 1;
            color: #495057;
        }

        .table-link {
            color: inherit !important;
            text-decoration: none !important;
            cursor: pointer;
        }

        .table-link:hover {
            text-decoration: underline !important;
        }

        /* Highlight rows with memo_id */
        tr.memo-assigned {
            background-color: #e6f7ff !important;
            position: relative;
        }

        tr.memo-assigned:hover {
            background-color: #cceeff !important;
        }

        tr.memo-assigned td:first-child::before {
            content: "📝";
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            margin-left: 3px;
        }

        /* Improved card styling */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background: var(--brand-table-header-bg);
            border-bottom: 1px solid var(--brand-border);
            padding: 15px 20px;
            border-radius: 10px 10px 0 0 !important;
        }

        .card-body {
            padding: 20px;
        }

        /* Button styling */
        .btn-brand-primary,
        .btn-success {
            transition: all 0.2s ease;
        }

        .btn-brand-primary:hover,
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Table improvements */
        .table {
            border-collapse: separate;
            border-spacing: 0;
        }

        .table th {
            background-color: var(--brand-table-header-bg);
            border-bottom: 2px solid var(--brand-border);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        /* Tooltip enhancement */
        [data-bs-toggle="tooltip"] {
            position: relative;
        }

        /* Memo badge */
        .memo-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #0dcaf0;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }

    </style>

    @push('scripts')
        <!-- DataTables JS -->
        <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

        <script>
            $(document).ready(function() {
                const formatDate = (date) => {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');

                    return `${year}-${month}-${day}`;
                };

                function loadListingQueryFromUrl() {
                    const params = new URLSearchParams(window.location.search);

                    if (params.has('gr_date_from')) {
                        $('#fromDate').val(params.get('gr_date_from'));
                    }

                    if (params.has('gr_date_to')) {
                        $('#toDate').val(params.get('gr_date_to'));
                    }

                    return params.has('gr_date_from') || params.has('gr_date_to');
                }

                function syncListingQueryToUrl() {
                    const params = new URLSearchParams();
                    const fromDate = $('#fromDate').val();
                    const toDate = $('#toDate').val();

                    if (fromDate) {
                        params.set('gr_date_from', fromDate);
                    }

                    if (toDate) {
                        params.set('gr_date_to', toDate);
                    }

                    const query = params.toString();
                    const nextUrl = query
                        ? `${window.location.pathname}?${query}`
                        : window.location.pathname;

                    window.history.replaceState({}, '', nextUrl);
                }

                function setDefaultDates() {
                    const today = new Date();
                    const oneWeekAgo = new Date();
                    const oneWeekAfter = new Date();
                    oneWeekAgo.setDate(today.getDate() - 7);
                    oneWeekAfter.setDate(today.getDate() + 7);

                    if (!$('#fromDate').val()) {
                        $('#fromDate').val(formatDate(oneWeekAgo));
                    }

                    if (!$('#toDate').val()) {
                        $('#toDate').val(formatDate(oneWeekAfter));
                    }
                }

                if (!loadListingQueryFromUrl()) {
                    setDefaultDates();
                }

                syncListingQueryToUrl();

                // Initialize DataTable
                var table = $('#lorryReceiptsTable').DataTable({
                    processing: true,
                    serverSide: true,
                    createdRow: function(row, data, dataIndex) {
                        // Make LR number cell clickable
                        var lrNumberCell = $(row).find(
                            'td:eq(1)'); // LR Number is in the 2nd column (index 1)
                        lrNumberCell.css('cursor', 'pointer');
                        lrNumberCell.on('click', function() {
                            showLRDetails(data.id);
                        });
                    },
                    ajax: {
                        url: '{{ route('lorry_receipts.index') }}',
                        type: 'GET',
                        data: function(d) {
                            d.gr_date_from = $('#fromDate').val();
                            d.gr_date_to = $('#toDate').val();
                        },
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    },
                    columns: [

                        {
                            data: 'gr_date_formatted',
                            name: 'gr_date'
                        },
                        {
                            data: 'lr_number',
                            name: 'lr_number',
                            render: function(data, type, row) {
                                return '<a href="#" class="lr-number-link" data-id="' + row.id + '">' +
                                    data + (row.linked_lr_id ?
                                        ' <span class="badge bg-info ms-1">[' + row.linked_lr_id +
                                        ']</span>' : '') + '</a>';
                            }
                        },
                        {
                            data: 'customer_name',
                            name: 'customer_name'
                        },
                        {
                            data: 'consignor_name',
                            name: 'consignor_name',
                            render: function(data, type, row) {
                                if (row.source_city) {
                                    return data + '<br><span class="city-label">' + row.source_city + '</span>';
                                }
                                return data;
                            }
                        },
                        {
                            data: 'consignee_name',
                            name: 'consignee_name',
                            render: function(data, type, row) {
                                if (row.destination_city) {
                                    return data + '<br><span class="city-label">' + row.destination_city + '</span>';
                                }
                                return data;
                            }
                        },
                        {
                            data: 'status_badge',
                            name: 'delivery_status'
                        },

                        {
                            data: 'truck_number',
                            name: 'truck_number'
                        },
                        {
                            data: 'weight',
                            name: 'weight',
                            className: 'text-end fw-medium'
                        },
                        {
                            data: 'fr_rate',
                            name: 'fr_rate',
                            className: 'text-end text-muted'
                        },
                        {
                            data: 'amount_formatted',
                            name: 'amount',
                            className: 'text-end fw-bold text-brand-primary'
                        },
                        {
                            data: 'actions',
                            name: 'actions',
                            orderable: false,
                            searchable: false,
                            className: 'text-center'
                        }
                    ],
                    order: [[0, 'desc']],
                    pageLength: 25,
                    responsive: false, // Disable responsive collapse
                    scrollX: true, // Enable DataTables horizontal scroll
                    autoWidth: false,
                    drawCallback: function(settings) {
                        $('[data-bs-toggle="tooltip"]').tooltip();
                    }
                });

                $('#filterBtn').on('click', function() {
                    syncListingQueryToUrl();
                    $('#lorryReceiptsTable').DataTable().ajax.reload();
                });

                $('#resetDateFilter').on('click', function() {
                    $('#fromDate').val('');
                    $('#toDate').val('');
                    syncListingQueryToUrl();
                    table.ajax.reload();
                });
            });

            // Helper function to format GR Date to DD-MM-YYYY
            function formatGRDate(dateString) {
                if (!dateString) return 'N/A';
                const parts = dateString.split('-')
                if (parts.length === 3 && parts[0].length === 4) {
                    return `${parts[2]}-${parts[1]}-${parts[0]}`;
                }
                return dateString;
            }

            // Show LR Details function - Optimized with merged API call
            async function showLRDetails(id) {
                const modalElement = document.getElementById('lrDetailsModal');
                const contentDiv = document.getElementById('lrDetailsContent');
                const modal = new bootstrap.Modal(modalElement);

                // Show loading state
                contentDiv.innerHTML = `
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading good receipt (bilty) details...</p>
                    </div>
                `;
                modal.show();

                try {
                    // Single API call that includes linked LRs if available
                    const response = await fetch(`/api/lorry-receipts/${id}/details`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const lr = await response.json();

                    // Build HTML content
                    let detailsHtml = '';

                    // Basic info and parties sections (only if not linked)
                    if (!lr.linked_lr_id) {
                        detailsHtml = `
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="bi bi-info-circle"></i> Basic Information</h6>
                                        </div>
                                        <div class="card-body">
                                            <table class="table table-borderless table-sm">
                                                <tr><td class="fw-bold">LR Number:</td><td>${lr.lr_number}</td></tr>
                                                <tr><td class="fw-bold">GR Date:</td><td>${formatGRDate(lr.gr_date)}</td></tr>
                                                <tr><td class="fw-bold">Status:</td><td><span class="badge ${lr.delivery_status === 'Delivered' ? 'bg-success' : 'bg-warning'}">${lr.delivery_status}</span></td></tr>
                                                <tr><td class="fw-bold">Invoice Number:</td><td>${lr.invoice_number || 'N/A'}</td></tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="bi bi-people"></i> Parties</h6>
                                        </div>
                                        <div class="card-body">
                                            <table class="table table-borderless table-sm">
                                                <tr><td class="fw-bold">Customer:</td><td>${lr.customer?.name || 'N/A'}</td></tr>
                                                <tr><td class="fw-bold">Consignor:</td><td>${lr.consignor?.name || 'N/A'}</td></tr>
                                                <tr><td class="fw-bold">Consignee:</td><td>${lr.consignee?.name || 'N/A'}</td></tr>
                                                <tr><td class="fw-bold">Transporter:</td><td>${lr.transporter?.name || 'N/A'}</td></tr>
                                                <tr><td class="fw-bold">Reference By:</td><td>${lr.reference?.name || 'N/A'}</td></tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-12">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="bi bi-truck"></i> Transport Details</h6>
                                        </div>
                                        <div class="card-body">
                                            <table class="table table-borderless table-sm">
                                                <tr><td class="fw-bold">Truck Number:</td><td>${lr.truck?.truck_number || 'N/A'}</td></tr>
                                                <tr><td class="fw-bold">Goods:</td><td>${lr.good_type || 'N/A'}</td></tr>
                                                <tr><td class="fw-bold">Weight:</td><td>${lr.weight || 'N/A'}</td></tr>
                                                <tr><td class="fw-bold">Amount:</td><td class="fw-bold text-success">${lr.amount || '0'}</td></tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }

                    // Linked LRs section (if data available from merged API response)
                    let linkedLRSectionHtml = '';
                    if (lr.linked_lrs_data) {
                        const { common, lrs } = lr.linked_lrs_data;
                        const lrRows = lrs.map(linkedLR => `
                            <tr>
                                <td>${linkedLR.lr_number}</td>
                                <td>${linkedLR.customer || 'N/A'}</td>
                                <td>${linkedLR.consignor || 'N/A'}</td>
                                <td>${linkedLR.consignee || 'N/A'}</td>
                                <td>${linkedLR.good_type || 'N/A'}</td>
                                <td>${linkedLR.weight || 'N/A'}</td>
                                <td class="fw-bold text-success">${linkedLR.amount || '0'}</td>
                                <td><span class="badge bg-${linkedLR.status === 'delivered' ? 'success' : 'warning'}">${linkedLR.status || 'N/A'}</span></td>
                            </tr>
                        `).join('');

                        linkedLRSectionHtml = `
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-truck"></i> Common Details</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless table-sm">
                                        <tr><td class="fw-bold">Transporter:</td><td>${common.transporter || 'Varies'}</td></tr>
                                        <tr><td class="fw-bold">Truck Number:</td><td>${common.truck_number || 'Varies'}</td></tr>
                                        <tr><td class="fw-bold">GR Date:</td><td>${common.gr_date ? formatGRDate(common.gr_date) : 'Varies'}</td></tr>
                                        <tr><td class="fw-bold">Reference By:</td><td>${common.reference_by || 'Varies'}</td></tr>
                                    </table>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="bi bi-list"></i> Linked Good Receipts (Bilty)</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>LR Number</th>
                                                <th>Customer</th>
                                                <th>Consignor</th>
                                                <th>Consignee</th>
                                                <th>Goods</th>
                                                <th>Weight</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>${lrRows}</tbody>
                                    </table>
                                </div>
                            </div>
                        `;
                    }

                    // User information section
                    const creator = lr.created_by && lr.creator ? `${lr.creator.name} (${lr.creator.email})` : 'N/A';
                    const updater = lr.updated_by && lr.updater ? `${lr.updater.name} (${lr.updater.email})` : 'N/A';

                    detailsHtml += `
                        <div class="row mt-3">
                            <div class="col-md-12">${linkedLRSectionHtml}</div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-person-circle"></i> User Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-borderless table-sm">
                                            <tr><td class="fw-bold">Created By:</td><td>${creator}</td></tr>
                                            <tr><td class="fw-bold">Last Updated By:</td><td>${updater}</td></tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;

                    contentDiv.innerHTML = detailsHtml;

                    // Update edit button
                    const editBtn = document.getElementById('editLorryReceiptBtn');
                    if (editBtn) {
                        editBtn.href = `/lorry_receipts/${id}/edit`;
                    }
                } catch (error) {
                    console.error('Error fetching LR details:', error);
                    contentDiv.innerHTML = `
                        <div class="alert alert-danger m-3">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Error:</strong> Unable to load LR details. Please try again.
                        </div>
                    `;
                }
            }

            // LR Copy functionality
            let currentLrId = null;

            // Handle LR Copy button click
            $(document).on('click', '.lr-copy-btn', function() {
                currentLrId = $(this).data('lr-id');
            });

            function generateLrCopy(withFreight) {
                if (!currentLrId) {
                    alert('Error: LR ID not found. Please try again.');
                    return;
                }

                const url = '{{ route("reports.lr-copy-export") }}?lr_id=' + currentLrId + '&show_freight=' + (withFreight ? '1' : '0');
                window.open(url, '_blank');

                // Close modal and remove backdrop
                const modalElement = document.getElementById('lrCopyModal');
                const modal = bootstrap.Modal.getInstance(modalElement);

                if (modal) {
                    // Hide modal
                    modal.hide();

                    // Remove backdrop immediately
                    setTimeout(function() {
                        const backdrop = document.querySelector('.modal-backdrop');
                        if (backdrop) {
                            backdrop.remove();
                        }
                        // Remove modal-open class and styles from body
                        document.body.classList.remove('modal-open');
                        document.body.style.overflow = '';
                        document.body.style.paddingRight = '';
                    }, 100);
                } else {
                    // If modal instance doesn't exist, manually remove backdrop
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }
            }
        </script>
    @endpush
@endsection
