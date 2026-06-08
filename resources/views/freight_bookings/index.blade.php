@extends('layouts.app')

@push('styles')
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/searchpanes/2.2.0/css/searchPanes.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/select/1.7.0/css/select.bootstrap5.min.css">
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
            padding: 4px 8px !important;
            font-size: 11.5px !important;
            vertical-align: middle;
            border-color: rgba(0,0,0,0.05) !important;
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
            color: inherit;
            font-size: 14px;
        }

        .btn-action-info    { background: var(--brand-info) !important; color: white !important; }
        .btn-action-primary { background: var(--brand-primary) !important; color: white !important; }
        .btn-action-success { background: var(--brand-success) !important; color: white !important; }
        .btn-action-outline-success {
            background: white !important;
            color: var(--brand-success) !important;
            border: 1px solid var(--brand-success) !important;
        }

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

        /* Compact Status Legend */
        .status-legend {
            display: flex;
            gap: 12px;
            align-items: center;
            font-size: 11px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
            border: 1px solid rgba(0,0,0,0.1);
        }

        .legend-delivered { background-color: #d1e7dd; }
        .legend-cancelled { background-color: #f8d7da; }
        .legend-pending   { background-color: #ffffff; }

        .table-success { background-color: #d1e7dd !important; border-color: #badbcc !important; }
        .table-danger  { background-color: #f8d7da !important; border-color: #f5c2c7 !important; }

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

        /* last .row child of .dataTables_wrapper */
        .dataTables_wrapper .row:last-child {
            padding: 0 22px !important;
        }
    </style>
@endpush

@push('scripts')
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @if (session('warning'))
        <script>
            Swal.fire({
                icon: 'warning',
                title: 'Warning',
                text: "{{ session('warning') }}",
                confirmButtonColor: '#f39c12'
            });
        </script>
    @endif
@endpush

@section('content')
    <div class="container-fluid py-1 mt-2">
        <div class="d-flex justify-content-between align-items-center mb-2 px-1">
            <h3 class="mb-0 card-title animate__animated animate__fadeInDown">Good Hires (Lorry Hire)</h3>
            @if (session('success'))
                <span class="badge text-bg-dark border animate__animated animate__fadeIn"><i class="bi bi-check-circle-fill text-success me-1"></i> {{ session('success') }}</span>
            @endif
            @if (session('error'))
                <span class="badge text-bg-dark border animate__animated animate__fadeIn"><i class="bi bi-exclamation-triangle-fill text-danger me-1"></i> {{ session('error') }}</span>
            @endif
        </div>

        <!-- Search & Operations Section -->
        <div class="glass-card mb-2 animate__animated animate__fadeInDown">
            <div class="card-header py-1 d-flex justify-content-between align-items-center" style="background-color: rgba(0, 0, 0, 0.1) !important;">
                <div class="d-flex align-items-center gap-4">
                    <h6 class="card-title mb-0">Search & Filters</h6>
                    <div class="status-legend mb-0 d-none d-md-flex">
                        <div class="legend-item">
                            <div class="legend-color legend-delivered"></div>
                            <span class="small">Delivered</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color legend-cancelled"></div>
                            <span class="small">Cancelled</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color legend-pending"></div>
                            <span class="small">Pending</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button id="bulk-memo-id-btn" class="header-pill-btn btn btn-primary border-0" style="display: none; height: 32px; padding: 0 16px !important; font-size: 12px !important;" data-bs-toggle="modal" data-bs-target="#bulkMemoIdModal">
                        <i class="bi bi-clipboard-check"></i> Bulk Memo
                    </button>
                    <button id="voucher-payment-btn" class="header-pill-btn btn btn-brand-primary border-0" style="display: none; height: 32px; padding: 0 16px !important; font-size: 12px !important;">
                        <i class="bi bi-receipt"></i> Voucher
                    </button>
                    <button id="combined-payment-btn" class="header-pill-btn btn btn-warning border-0" style="display: none; height: 32px; padding: 0 16px !important; font-size: 12px !important;">
                        <i class="bi bi-collection"></i> Combined
                    </button>
                </div>
            </div>
            <div class="card-header" style="padding: 10px 16px !important;">
                <form method="GET" action="{{ route('freight_bookings.index') }}" id="searchForm">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <input type="text" class="form-control form-control-sm" name="search"
                                value="{{ \App\Support\ListingQueryString::scalarValue(request(), 'search', \App\Support\ListingQueryString::FREIGHT_BOOKING_KEYS) }}" placeholder="LR, Customer, Truck, City...">
                        </div>
                        <div class="col-md-3">
                            <div class="d-flex gap-2">
                                <div class="input-group-custom flex-grow-1">
                                    <span>From</span>
                                    <input type="date" name="start_date" value="{{ $startDate }}">
                                </div>
                                <div class="input-group-custom flex-grow-1">
                                    <span>To</span>
                                    <input type="date" name="end_date" value="{{ $endDate }}">
                                </div>
                            </div>
                        </div>
                        <!-- Pending Memo -->
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="pending_memo" value="1"
                                    id="pendingMemoFilter" {{ request('pending_memo') ? 'checked' : '' }}>
                                <label class="form-check-label" for="pendingMemoFilter">
                                    Pending Memo
                                </label>
                            </div>
                        </div>
                        <div class="col-md-1 d-flex gap-2 justify-content-end">
                            <button type="submit" class="filter-action-btn" style="background: var(--brand-primary); color: white;" title="Filter" data-bs-toggle="tooltip">
                                <i class="bi bi-funnel-fill"></i>
                            </button>
                            <a href="{{ route('freight_bookings.index') }}" class="filter-action-btn" style="background: var(--brand-text-primary); color: white;" title="Reset" data-bs-toggle="tooltip">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if (request('search'))
            <div class="alert alert-info py-1 px-3 mb-2 animate__animated animate__fadeIn" style="font-size: 11px;">
                <i class="bi bi-info-circle me-1"></i> Results for: <strong>"{{ request('search') }}"</strong>
                <a href="{{ route('freight_bookings.index') }}" class="btn btn-link btn-sm p-0 ms-2" style="font-size: 11px;">Clear Search</a>
            </div>
        @endif

        <div class="glass-card animate__animated animate__fadeInUp">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="freightBookingsTable" class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;" class="text-center"><input type="checkbox" id="select-all"></th>
                                <th class="text-center">GR Date</th>
                                <th>LR No</th>
                                <th>Customer</th>
                                <th>Transporter</th>
                                <th>Truck No</th>
                                <th>Route</th>
                                <th>Weight</th>
                                <th>Rate</th>
                                <th>Total</th>
                                <th class="text-end">Balance</th>
                                <th class="text-center" style="width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Combined Payment Modal -->
    <div class="modal fade" id="combinedPaymentModal" tabindex="-1" aria-labelledby="combinedPaymentModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="combinedPaymentModalLabel">🧾 Combined Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="combinedPaymentBody">
                    <!-- Dynamic content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="combinedPaymentForm" class="btn btn-warning">💳 Submit Combined
                        Payment</button>
                </div>
            </div>
        </div>
    </div>

    <!-- LR Details Modal -->
    <div class="modal fade" id="lrDetailsModal" tabindex="-1" aria-labelledby="lrDetailsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="lrDetailsModalLabel">LR Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="lrDetailsContent">
                    <!-- Content will be loaded dynamically -->
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Voucher Payment Modal -->
    <div class="modal fade" id="voucherPaymentModal" tabindex="-1" aria-labelledby="voucherPaymentModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="voucherPaymentModalLabel">💳 Voucher Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="voucherPaymentBody">
                    <!-- Dynamic content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="submitVoucherPayment()">💰 Process
                        Payment</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Individual Voucher Payment Modal -->
    <div class="modal fade" id="individualPaymentModal" tabindex="-1" aria-labelledby="individualPaymentModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="individualPaymentModalLabel">💳 Individual Voucher Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="individualPaymentBody">
                    <!-- Dynamic content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="submitIndividualPayment">💰 Process
                        Payment</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Memo ID Modal -->
    <div class="modal fade" id="bulkMemoIdModal" tabindex="-1" aria-labelledby="bulkMemoIdModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-clipboard-check"></i> Bulk Memo ID Assignment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="bulkMemoIdBody">
                    <!-- Dynamic content will be inserted here -->
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="bulkMemoIdForm" class="btn btn-primary">Assign Memo ID</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const paymentTypes = @json($paymentTypes);
        const paymentModes = @json($paymentModes);
        let selectedRows = {};
        let selectedBookingIds = [];

        $(document).on('change', '.row-checkbox', function() {
            const id = $(this).val();

            if (this.checked) {
                selectedRows[id] = {
                    id: id,
                    lr: $(this).data('lr'),
                    truck: $(this).data('truck'),
                    balance: parseFloat($(this).data('balance') || 0),
                    transporterId: $(this).data('transporter-id'),
                    customerId: $(this).data('customer-id'),
                    memoId: $(this).data('memo-id'),
                    linkedLrId: $(this).data('linked-lr-id'),
                    voucher_number: $(this).data('voucher_number'),
                    freight_rate: parseFloat($(this).data('freight-rate') || 0),
                    freight_booking_id: $(this).data('freight-booking-id'),
                    lr_id: $(this).data('lr-id'),
                    transporter: $(this).data('transporter'),
                    lr_transporter: $(this).data('lr-transporter'),
                        transporter_mobile_number: $(this).data('lr-transporter-mobile-number'),
                        transporter_short_name: $(this).data('lr-transporter-short-name'),
                        transporter_beneficiary_id: $(this).data('lr-transporter-beneficiary-id'),
                    fb_transporter: $(this).data('fb-transporter'),
                    tds_percentage: $(this).data('tds-percentage'),
                    amount: $(this).data('amount'),
                    shortage_amount: $(this).data('shortage-amount'),
                    damage_amount: $(this).data('damage-amount'),
                    advance_amount: $(this).data('advance-amount')
                };
                if (!selectedBookingIds.includes(id)) {
                    selectedBookingIds.push(id);
                }
            } else {
                delete selectedRows[id];
                selectedBookingIds = selectedBookingIds.filter(x => x !== id);
            }
        });

        $('#select-all').on('change', function() {
            const checked = this.checked;

            $('.row-checkbox').each(function() {
                const id = $(this).val();
                $(this).prop('checked', checked);

                if (checked) {
                    if (!selectedBookingIds.includes(id)) {
                        selectedBookingIds.push(id);
                        selectedRows[id] = {
                            id: id,
                            lr: $(this).data('lr'),
                            truck: $(this).data('truck'),
                            balance: parseFloat($(this).data('balance') || 0),
                            transporterId: $(this).data('transporter-id'),
                            customerId: $(this).data('customer-id'),
                            memoId: $(this).data('memo-id'),
                            linkedLrId: $(this).data('linked-lr-id'),
                            voucher_number: $(this).data('voucher_number'),
                            freight_rate: parseFloat($(this).data('freight-rate') || 0),
                            freight_booking_id: $(this).data('freight-booking-id'),
                                    lr_id: $(this).data('lr-id'),
                                    transporter: $(this).data('transporter'),
                                    lr_transporter: $(this).data('lr-transporter'),
                                    transporter_mobile_number: $(this).data('lr-transporter-mobile-number'),
                                    transporter_short_name: $(this).data('lr-transporter-short-name'),
                                    transporter_beneficiary_id: $(this).data('lr-transporter-beneficiary-id'),
                                    fb_transporter: $(this).data('fb-transporter'),
                                    tds_percentage: $(this).data('tds-percentage'),
                                    amount: $(this).data('amount'),
                                    shortage_amount: $(this).data('shortage-amount'),
                                    damage_amount: $(this).data('damage-amount'),
                                    advance_amount: $(this).data('advance-amount')
                        };
                    }
                } else {
                    selectedBookingIds = [];
                    selectedRows = {};
                }
            });

            updatePaymentButtonsVisibility();
        });

        function updateSelectAllState() {
            const total = $('.row-checkbox').length;
            const checked = Object.values(selectedRows).length;

            if (checked === 0) {
                $('#select-all').prop({
                    checked: false,
                    indeterminate: false
                });
            } else if (checked === total) {
                $('#select-all').prop({
                    checked: true,
                    indeterminate: false
                });
            } else {
                $('#select-all').prop({
                    indeterminate: true
                });
            }
        }

        function getSelectedCheckboxes() {
            return selectedBookingIds
                .map(id => $('.row-checkbox[value="' + id + '"]'))
                .filter(cb => cb.length);
        }
    </script>

    <script>
        $(document).ready(function() {
            // Select all checkbox functionality
            $('#select-all').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.row-checkbox').prop('checked', isChecked);
                updatePaymentButtonsVisibility();
            });

            // Individual checkbox change
            $(document).on('change', '.row-checkbox', function() {
                updatePaymentButtonsVisibility();

                // Update select-all checkbox state
                const totalCheckboxes = $('.row-checkbox').length;
                const checkedCheckboxes = Object.values(selectedRows).length;

                if (checkedCheckboxes === 0) {
                    $('#select-all').prop('indeterminate', false).prop('checked', false);
                } else if (checkedCheckboxes === totalCheckboxes) {
                    $('#select-all').prop('indeterminate', false).prop('checked', true);
                } else {
                    $('#select-all').prop('indeterminate', true);
                }
            });

            // Voucher Payment Modal
            $('#voucher-payment-btn').on('click', function() {
                const checkedBoxes = Object.values(selectedRows);
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one good hire (lorry hire).');
                    return;
                }

                let tableBody = '';
                let totalAmount = 0;

                checkedBoxes.forEach(function(data) {
                    const lrNumber = data.lr;
                    const truckNumber = data.truck;
                    const balance = data.balance;
                    const voucherNumber = data.voucher_number || '-';

                    totalAmount += balance;

                    tableBody += `
                        <tr>
                            <td>${lrNumber}</td>
                            <td>${truckNumber}</td>
                            <td>${voucherNumber}</td>
                            <td class="text-end">${balance.toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                            <td>
                                <input type="number" class="form-control payment-amount allocated-input"
                                      data-id="${data.id}"
                                      value="${balance}"
                                      min="0.01"
                                      max="${balance}"
                                      step="0.01">
                                <div class="form-text">Amount must be greater than zero and not exceed balance</div>
                            </td>
                        </tr>
                    `;
                });

                const modalContent = `
                    <form id="voucherPaymentForm" method="POST" action="{{ route('freight_bookings.voucher_payment') }}">
                        @csrf
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Payment Type</label>
                                <select name="payment_type_id" id="paymentType" class="form-select" required>
                                    <option value="">Select Payment Type</option>
                                    @foreach ($paymentTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Mode</label>
                                <select name="payment_mode_id" id="paymentMode" class="form-select" required>
                                    <option value="">Select Payment Mode</option>
                                    @foreach ($paymentModes as $mode)
                                        <option value="{{ $mode->id }}">{{ $mode->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>LR Number</th>
                                        <th>Truck Number</th>
                                        <th>Voucher Number</th>
                                        <th class="text-end">Balance Amount</th>
                                        <th class="text-end">Payment Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tableBody}
                                </tbody>
                                <tfoot>
                                    <tr class="table-info">
                                        <th colspan="4" class="text-end">Total Payment:</th>
                                        <th class="text-end" id="totalPaymentAmount">${totalAmount.toLocaleString('en-IN', {minimumFractionDigits: 2})}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" id="paymentRemarks" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reference ID (Optional)</label>
                            <input type="text" name="reference_id" id="referenceId" class="form-control">
                        </div>
                    </form>
                `;

                $('#voucherPaymentBody').html(modalContent);
            });

            // Combined Payment Button Click Handler
            const planEndDateStr = @json(Auth::user()->company->plan_end_date ?? null);
            const today = new Date();
            $('#combined-payment-btn').on('click', function() {
                const checkedBoxes = Object.values(selectedRows);
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one booking.');
                    return;
                }
                if (planEndDateStr) {
                    const planEndDate = new Date(planEndDateStr);
                    if (today > planEndDate) {
                        alert(
                            'Your subscription plan has expired. Please renew your subscription to continue using the service.'
                        );
                        // Stop further execution so modal doesn't open
                        return false; // or just return
                    }
                }

                // Get first selected checkbox data
                const firstData = checkedBoxes[0];
                const linkedLrId = firstData.linkedLrId;

                // If there's a linked LR ID, fetch all related LRs
                if (linkedLrId) {
                    $.get(`/lorryreceipts/by-linked/${linkedLrId}`, function(response) {
                        const lrsArray = response.lrs || response;

                        if (lrsArray && lrsArray.length > 0) {
                            // ✅ CHECK: Verify all linked LRs have freight_booking_id
                            const allHaveFreightBooking = lrsArray.every(lr =>
                                lr.freight_booking_id !== null &&
                                lr.freight_booking_id !== undefined &&
                                lr.freight_booking_id !== ''
                            );

                            if (!allHaveFreightBooking) {
                                // Find which LRs are missing freight bookings
                                const missingLRs = lrsArray.filter(lr =>
                                    lr.freight_booking_id === null ||
                                    lr.freight_booking_id === undefined ||
                                    lr.freight_booking_id === ''
                                );

                                const lrNumbers = missingLRs.map(lr => lr.lr_number).join(', ');

                                alert(
                                    `⚠️ Error: The following linked LRs do not have Good Hires (Lorry Hire):\n\n${lrNumbers}\n\nPlease create FRs for all linked LRs before proceeding with combined payment.`
                                );
                                return;
                            }

                            // All checks passed, proceed with modal
                            buildCombinedModal(lrsArray);
                        } else {
                            // Fallback to just the selected LRs if no linked ones found
                            const lrData = checkedBoxes.map(data => {
                                return {
                                    freight_booking_id: data.freight_booking_id,
                                    lr_number: data.lr,
                                    truck_number: data.truck,
                                    balance_amount: data.balance
                                };
                            });
                            buildCombinedModal(lrData);
                        }
                    }).fail(function(error) {
                        console.error('Error fetching linked LRs:', error);
                        alert('Error loading linked LRs. Please try again.');
                    });
                } else {
                    // No linked LR ID, just use the selected rows
                    const lrData = checkedBoxes.map(data => {
                        return {
                            freight_booking_id: data.freight_booking_id,
                            lr_number: data.lr,
                            truck_number: data.truck,
                            balance_amount: data.balance
                        };
                    });
                    buildCombinedModal(lrData);
                }
            });
            // In your combined payment button click handler, after fetching the LRs:
            function buildCombinedModal(lrData) {
                let rowsHtml = '';
                const bookingIds = [];
                let totalBalance = 0;

                // First calculate total balance
                lrData.forEach(lr => {
                    totalBalance += parseFloat(lr.balance_amount) || 0;
                });

                // Then build rows
                lrData.forEach(lr => {
                    const fbId = lr.freight_booking_id;
                    const lrNo = lr.lr_number;
                    const truckNo = lr.truck_number;
                    const balance = parseFloat(lr.balance_amount) || 0;
                    const percentage = totalBalance > 0 ? (balance / totalBalance) * 100 : 0;

                    bookingIds.push(fbId);

                    rowsHtml += `
                        <tr>
                            <td>${lrNo}</td>
                            <td>${truckNo}</td>
                                <input type="hidden"
                                    class="form-control form-control-sm allocated-input"
                                    name="payment_amounts[]"
                                    data-fb-id="${fbId}"
                                    data-balance="${balance}"
                                    min="${Math.min(balance, 0)}" max="${Math.max(balance, 0)}"
                                    step="0.01"
                                    value="${balance.toFixed(2)}">
                        </tr>
                    `;
                });

                // Build the modal content with the existing structure
                const modalContent = `
                    <form id="combinedPaymentForm" method="POST" action="{{ route('freight_bookings.store_combined_payment') }}">
                        @csrf
                        <div class="alert alert-secondary d-flex align-items-center justify-content-between">
                            <div>
                                <strong>Total Balance of Selected:</strong> <span id="combinedTotalBalance">${totalBalance.toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <label class="form-label mb-0">Combined Amount *</label>
                                <div>
                                    <input type="number"
                                          step="0.01"
                                          min="0.01"
                                          max="${totalBalance}"
                                          class="form-control"
                                          id="combinedAmountInput"
                                          name="combined_amount"
                                          style="width: 200px;"
                                          value="${totalBalance.toFixed(2)}"
                                          required>
                                    <div class="invalid-feedback">
                                        Amount must be between 0.01 and ${totalBalance.toLocaleString('en-IN', {minimumFractionDigits: 2})}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive mb-2">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>LR Number</th>
                                        <th>Truck Number</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${rowsHtml}
                                </tbody>
                                <tfoot class="d-none">
                                    <tr class="table-info">
                                        <th colspan="3" class="text-end">Total Allocated:</th>
                                        <th class="text-end" id="combinedAllocatedTotal">${totalBalance.toLocaleString('en-IN', {minimumFractionDigits: 2})}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        ${bookingIds.map(id => `<input type="hidden" name="booking_ids[]" value="${id}">`).join('')}
                        <div id="combinedAmountsContainer"></div>
                        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                            <div style="flex: 1;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Payment Type *</label>
                                <select id="paymentType" name="payment_type" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
                                    <option value="">Select Payment Type</option>
                                    ${paymentTypes.map(type =>
                                        `<option value="${type.id}">${type.name}</option>`
                                    ).join('')}
                                </select>
                            </div>
                            <div style="flex: 1;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Payment Mode *</label>
                                <select id="paymentMode" name="payment_mode" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
                                    <option value="">Select Payment Mode</option>
                                    ${paymentModes.map(mode =>
                                        `<option value="${mode.id}">${mode.name}</option>`
                                    ).join('')}
                                </select>
                            </div>
                            <div id="pumpNameContainer" style="display: none; margin-bottom: 15px; flex: 1;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Pump Name *</label>
                                <select id="pumpName" name="pump_id" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                    <option value="">Select Pump</option>
                                    @foreach ($pumps as $pump)
                                        <option value="{{ $pump->id }}">{{ $pump->pump_name }} ({{ $pump->contact_name }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                            <div style="flex: 1;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Payment Date</label>
                                <input type="date" id="paymentDate" name="payment_date" value="{{ date('Y-m-d') }}" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
                            </div>
                            <div style="flex: 1;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Remarks</label>
                                <textarea id="paymentRemarks" name="remarks" style="width: 100%; height: 60px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" placeholder="Enter any remarks..."></textarea>
                            </div>
                        </div>
                    </form>
                `;

                // Update modal content
                $('#combinedPaymentBody').html(modalContent);

                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('combinedPaymentModal'));
                modal.show();

                // Initialize amount allocation
                initializeAmountAllocation(totalBalance);
            }

            function initializeAmountAllocation(totalBalance) {
                // Handle allocation when combined amount changes
                $(document).off('input', '#combinedAmountInput').on('input', '#combinedAmountInput', function() {
                    const totalAmount = parseFloat($(this).val()) || 0;
                    const maxAmount = parseFloat($(this).attr('max')) || 0;

                    // Validation
                    if (totalAmount > maxAmount || totalAmount < 0) {
                        $(this).addClass('is-invalid');
                        return;
                    }
                    $(this).removeClass('is-invalid');

                    // Percentage-based allocation logic
                    // First, calculate total balance from all inputs
                    let totalBalanceAmount = 0;
                    $('.allocated-input').each(function() {
                        totalBalanceAmount += parseFloat($(this).data('balance')) || 0;
                    });

                    let allocatedTotal = 0;
                    let allocations = [];

                    // Calculate percentage-based allocation for each booking
                    $('.allocated-input').each(function() {
                        const $input = $(this);
                        const balance = parseFloat($input.data('balance')) || 0;

                        // Calculate percentage of total balance
                        const percentage = totalBalanceAmount > 0 ? (balance / totalBalanceAmount) : 0;

                        // Allocate based on percentage
                        let alloc = totalAmount * percentage;

                        // Ensure allocation doesn't exceed balance
                        alloc = Math.min(alloc, balance);

                        allocations.push({
                            $input: $input,
                            alloc: alloc
                        });

                        allocatedTotal += alloc;
                    });

                    // Handle rounding differences
                    const difference = totalAmount - allocatedTotal;
                    if (Math.abs(difference) > 0.01) {
                        // Distribute rounding difference to the first booking with remaining balance
                        for (let i = 0; i < allocations.length; i++) {
                            const item = allocations[i];
                            const balance = parseFloat(item.$input.data('balance')) || 0;
                            const currentAlloc = item.alloc;
                            const remaining = balance - currentAlloc;

                            if (remaining > 0 && difference > 0) {
                                const adjustment = Math.min(remaining, Math.abs(difference));
                                item.alloc += adjustment;
                                allocatedTotal += adjustment;
                                break; // Stop after adjusting first available
                            }
                        }
                    }

                    // Apply allocations
                    allocations.forEach(item => {
                        item.$input.val(item.alloc.toFixed(2));
                    });

                    // Update total allocated display
                    $('#combinedAllocatedTotal').text(
                        '' + totalAmount.toLocaleString('en-IN', {
                            minimumFractionDigits: 2
                        })
                    );
                });

                // Handle manual input changes in individual allocations
                $(document).off('input', '.allocated-input').on('input', '.allocated-input', function() {
                    const $input = $(this);
                    const val = parseFloat($input.val()) || 0;
                    const max = parseFloat($input.attr('max')) || 0;

                    // Validation
                    if (val > max || val < 0) {
                        $input.addClass('is-invalid');
                    } else {
                        $input.removeClass('is-invalid');
                    }

                    // Recalculate total allocated
                    let allocatedTotal = 0;
                    $('.allocated-input').each(function() {
                        allocatedTotal += parseFloat($(this).val()) || 0;
                    });

                    // Update totals
                    $('#combinedAllocatedTotal').text(
                        '' + allocatedTotal.toLocaleString('en-IN', {
                            minimumFractionDigits: 2
                        })
                    );
                    $('#combinedAmountInput').val(allocatedTotal.toFixed(2));
                });
            }

            // Handle submit of Combined Payment via AJAX
            $(document).off('submit', '#combinedPaymentForm').on('submit', '#combinedPaymentForm', function(e) {
                e.preventDefault();
                if (!confirm(
                        "Please review all payment details carefully before proceeding.\nOnce the voucher status is marked as PAID, it cannot be edited or changed.\nEnsure the information is correct before saving."
                    )) {
                    return;
                }
                const form = this;
                const url = $(form).attr('action');
                const formData = new FormData(form);
                fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                .getAttribute('content')
                        },
                        body: formData
                    })
                    .then(async (res) => {
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || data.success === false) {
                            // Check if this is a duplicate warning
                            if (data.warning && data.duplicate_details) {
                                const duplicate = data.duplicate_details;
                                const confirmMessage = `⚠️ DUPLICATE PAYMENT WARNING ⚠️\n\n` +
                                    `A similar payment already exists for booking ID ${data.booking_index || 'N/A'}:\n\n` +
                                    `Date: ${duplicate.date}\n` +
                                    `Amount: ${duplicate.amount}\n` +
                                    `Payment Type: ${duplicate.payment_type}\n` +
                                    `Payment Mode: ${duplicate.payment_mode}\n` +
                                    `Created: ${duplicate.created_at}\n\n` +
                                    `Do you want to proceed with creating this duplicate payment?`;

                                return Swal.fire({
                                    icon: 'warning',
                                    title: 'Duplicate Payment Detected',
                                    html: `<div style="text-align: left;">
                                        <p><strong>A similar payment already exists:</strong></p>
                                        <ul style="text-align: left;">
                                            <li><strong>Date:</strong> ${duplicate.date}</li>
                                            <li><strong>Amount:</strong> ${duplicate.amount}</li>
                                            <li><strong>Payment Type:</strong> ${duplicate.payment_type}</li>
                                            <li><strong>Payment Mode:</strong> ${duplicate.payment_mode}</li>
                                            <li><strong>Created:</strong> ${duplicate.created_at}</li>
                                        </ul>
                                        <p>Do you want to proceed with creating this duplicate payment?</p>
                                    </div>`,
                                    showCancelButton: true,
                                    confirmButtonText: 'Yes, Proceed',
                                    cancelButtonText: 'Cancel',
                                    confirmButtonColor: '#ffc107',
                                    cancelButtonColor: '#6c757d'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        // Add confirm_duplicate flag and resubmit
                                        formData.append('confirm_duplicate', '1');

                                        // Resubmit the form
                                        return fetch(url, {
                                            method: 'POST',
                                            headers: {
                                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                                    .getAttribute('content')
                                            },
                                            body: formData
                                        })
                                        .then(async (res) => {
                                            const data = await res.json().catch(() => ({}));
                                            if (!res.ok || data.success === false) {
                                                throw new Error(data.message || 'Failed to process combined payment');
                                            }
                                            return data;
                                        });
                                    } else {
                                        throw new Error('Payment cancelled by user');
                                    }
                                });
                            }
                            throw new Error(data.message || 'Failed to process combined payment');
                        }
                        return data;
                    })
                    .then((data) => {
                        $('#combinedPaymentModal').modal('hide');
                        let successText = data.message || 'Combined payment processed successfully';
                        if (data.adjust_in_advance && data.remaining_advance_balance !== undefined) {
                            const name = data.transporter_name || 'Transporter';
                            const bal = parseFloat(data.remaining_advance_balance).toFixed(2);
                            successText += '\n\nRemaining advance balance (' + name + '):  ' + bal;
                        }
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: successText,
                            confirmButtonColor: '#2FB36F'
                        }).then(() => {
                            window.location.reload();
                        });
                    })
                    .catch((err) => {
                        if (err.message !== 'Payment cancelled by user') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Payment Error',
                                text: err.message ||
                                    'An error occurred while processing combined payment.',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    });
            });

            // Bulk Memo ID Modal
            $('#bulk-memo-id-btn').on('click', function() {
                const checkedBoxes = Object.values(selectedRows);
                const planEndDateStr = @json(Auth::user()->company->plan_end_date ?? null);
                const today = new Date();
                if (planEndDateStr && new Date(planEndDateStr) < today) {
                    alert('Your plan has expired. Please renew your plan to use this feature.');
                    return false;
                }

                if (checkedBoxes.length === 0) {
                    alert('Please select at least one good hire (lorry hire).');
                    return;
                }

                let tableBody = '';
                let lrIds = [];
                let freightBookingIds = [];
                let netAmount = 0;

                checkedBoxes.forEach(function(data) {
                    const lrNumber = data.lr;
                    const truckNumber = data.truck;
                    const amount = parseFloat(data.amount) || 0;
                    const freightRate = parseFloat(data.freight_rate || 0);
                    const freightBookingId = data.freight_booking_id;
                    const shortageAmount = parseFloat(data.shortage_amount) || 0;
                    const damageAmount = parseFloat(data.damage_amount) || 0;
                    netAmount += amount;

                    // Get LR ID from the freight booking (we'll need to add this data attribute)
                    const lrId = data.lr_id; // We'll need to add this

                    if (lrId) {
                        lrIds.push(lrId);
                    }
                    freightBookingIds.push(freightBookingId);

                    tableBody += `
                        <tr>
                            <td>${lrNumber}</td>
                            <td>${truckNumber}</td>
                            <td class="text-end">${freightRate.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                            <td class="text-end">${amount + shortageAmount + damageAmount}</td>
                            <td class="text-end">${shortageAmount}</td>
                            <td class="text-end">${damageAmount}</td>
                            <input type="hidden" name="lr_ids[]" value="${lrId}">
                            <input type="hidden" name="freight_booking_ids[]" value="${freightBookingId}">
                        </tr>
                    `;
                });

                const modalContent = `
                    <form id="bulkMemoIdForm" method="POST" action="{{ route('freight_bookings.bulk_memo_id') }}">
                        @csrf
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">Memo ID *</label>
                                <input type="text" name="memo_id" class="form-control" required placeholder="Enter Memo ID">
                                <div class="form-text">This memo ID will be assigned to all selected good receipts (bilty)</div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>LR Number</th>
                                        <th>Truck Number</th>
                                        <th class="text-end">Freight Rate</th>
                                        <th class="text-end">LR Amount</th>
                                        <th class="text-end">Shortage</th>
                                        <th class="text-end">Damage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tableBody}
                                </tbody>
                                <tfoot>
                                    <tr class="table-info">
                                        <th colspan="5" class="text-end">Net Amount:</th>
                                        <th class="text-end">${netAmount.toLocaleString('en-IN', {minimumFractionDigits: 2})}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Note:</strong> Assigning memo ID will:
                            <ul class="mb-0 mt-0">
                                <li>Set good receipt (bilty) status to "Delivered"</li>
                                <li>Mark associated good hires (lorry hire) as delivered</li>
                                <li>This action cannot be easily undone</li>
                            </ul>
                        </div>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Loose Weight System:</strong>
                            <ul class="mb-0 mt-0">
                                <li>Voucher payments and invoices are generated against the <strong>lower</strong> of loading weight and unloading weight</li>
                                <li>TDS deductions for transporters are <strong>always</strong> based on the loading weight</li>
                                <li>After delivery, you have to edit the unloading weight from the FR edit page</li>
                            </ul>
                        </div>
                    </form>
                `;

                $('#bulkMemoIdBody').html(modalContent);
            });

            // Payment amount change handler
            $(document).on('input', '.payment-amount', function() {
                let total = 0;
                $('.payment-amount').each(function() {
                    total += parseFloat($(this).val()) || 0;
                });
                $('#totalPaymentAmount').text('' + total.toLocaleString('en-IN', {
                    minimumFractionDigits: 2
                }));
            });
        });

        // Function to open voucher payment modal
        function openVoucherPaymentModal() {
            updatePaymentButtonsVisibility();
            $('#voucherPaymentModal').modal('show');
        }

        function updatePaymentButtonsVisibility() {
            const checkedBoxes = Object.values(selectedRows);
            $('#bulk-memo-id-btn, #voucher-payment-btn, #combined-payment-btn').hide();
            if (checkedBoxes.length === 0) {
                return; // nothing selected
            }
            if (checkedBoxes.length === 1) {
                const singleData = checkedBoxes[0];
                const singleLinkedLrId = singleData.linkedLrId;
                if (!singleLinkedLrId) {
                    $('#voucher-payment-btn').show(); // show only if linked_lr_id is null
                }
                if (singleLinkedLrId) {
                    $('#combined-payment-btn').show();
                }

                const singleMemoId = singleData.memoId;
                if (!singleMemoId) {
                    $('#bulk-memo-id-btn').show();
                }
                return;
            }
            let firstCustomerId = null;
            let firstTransporterId = null;
            let firstLinkedLrId = null;
            let allSameCustomer = true;
            let allSameTransporter = true;
            let allSameLinked = true;
            let anyLinkedLrExists = false;
            let allSameMemoId = true;

            checkedBoxes.forEach(function(data) {
                const customerId = data.customerId;
                const transporterId = data.transporterId;
                const linkedLrId = data.linkedLrId || '';
                const memoId = data.memoId;

                if (linkedLrId) {
                    anyLinkedLrExists = true;
                }
                if (memoId) {
                    allSameMemoId = false;
                }

                if (firstCustomerId === null) {
                    firstCustomerId = customerId;
                    firstTransporterId = transporterId;
                    firstLinkedLrId = linkedLrId;
                } else {
                    if (customerId !== firstCustomerId) {
                        allSameCustomer = false;
                    }
                    if (transporterId !== firstTransporterId) {
                        allSameTransporter = false;
                    }
                    if (linkedLrId !== firstLinkedLrId) {
                        allSameLinked = false;
                    }
                }
            });

            // 1. Bulk Memo ID button → if customer same
            if (allSameCustomer) {
                if (allSameMemoId) {
                    $('#bulk-memo-id-btn').show();
                }
            }

            // 2. Voucher Payment button → if transporter same
            if (allSameTransporter) {
                if (!anyLinkedLrExists) {
                    $('#voucher-payment-btn').show();
                }
            }

            // 3. Combined Payment button → if linked_lr_id same and not empty
            if (allSameLinked && firstLinkedLrId) {
                $('#combined-payment-btn').show();
            }
        }
    </script>
@endpush

<style>
    /* Modal styles */
    .lr-details-container {
        padding: 1.5rem;
    }

    .lr-details-section {
        margin-bottom: 1.5rem;
        border-radius: 0.5rem;
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
        overflow: hidden;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }

    .lr-details-section h5 {
        background-color: #f1f8ff;
        padding: 0.75rem 1.25rem;
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: #0d6efd;
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

    /* Animation effects */
    .wavy-animation {
        animation: waveMove 2s ease-in-out infinite;
        display: inline-block;
    }

    .wavy-animation:nth-child(1) {
        animation-delay: 0s;
    }

    .wavy-animation:nth-child(2) {
        animation-delay: 0.3s;
    }

    @keyframes waveMove {
        0% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-5px);
        }

        100% {
            transform: translateY(0);
        }
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

    /* Table links */
    .table-link {
        color: inherit !important;
        text-decoration: none !important;
        cursor: pointer;
    }

    .table-link:hover {
        text-decoration: underline !important;
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
        background: linear-gradient(to right, #f8f9fa, #e9ecef);
        border-bottom: 1px solid #e9ecef;
        padding: 15px 20px;
        border-radius: 10px 10px 0 0 !important;
    }

    .card-body {
        padding: 20px;
    }

    /* Button styling */
    .btn-brand-primary,
    .btn-success,
    .btn-primary,
    .btn-warning {
        transition: all 0.2s ease;
    }

    .btn-brand-primary:hover,
    .btn-success:hover,
    .btn-primary:hover,
    .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    /* Table improvements */
    .table {
        border-collapse: separate;
        border-spacing: 0;
    }

    .table th {
        background-color: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
    }

    /* Tooltip enhancement */
    [data-bs-toggle="tooltip"] {
        position: relative;
    }
</style>

@push('scripts')
    <script>
        $(document).ready(function() {
            // Initialize tooltips
            $('[data-bs-toggle="tooltip"]').tooltip();

            // Voucher Payment Modal
            $('#voucher-payment-btn').on('click', function() {
                const checkedBoxes = Object.values(selectedRows);

                if (checkedBoxes.length === 0) {
                    alert('Please select at least one good hire (lorry hire).');
                    return;
                }

                // ✅ Collect transporter IDs from selected rows
                let transporterIds = [];
                checkedBoxes.forEach(function(data) {
                    transporterIds.push(data.transporterId);
                });

                // ✅ Check if all transporter IDs are the same
                const uniqueTransporters = [...new Set(transporterIds)];
                if (uniqueTransporters.length > 1) {
                    alert('You can only create a voucher for bookings from the same transporter.');
                    $('#voucherPaymentModal').hide();

                    return;
                }
                // Clear previous data
                $('#voucherPaymentTableBody').empty();

                let totalBalance = 0;
                checkedBoxes.forEach(function(data) {
                    const lrNumber = data.lr;
                    const balance = data.balance;
                    const freightBookingId = data.freight_booking_id;
                    const voucherNumber = data.voucher_number || '-';

                    totalBalance += balance;

                    const row = `
                      <tr>
                          <td>${lrNumber}</td>
                          <td>${voucherNumber}</td>
                          <td class="text-end">${balance.toFixed(2)}</td>
                          <td>
                              <input type="number" class="form-control form-control-sm payment-amount"
                                    name="payments[${freightBookingId}]"
                                    min="${Math.min(balance, 0)}" max="${Math.max(balance, 0)}" step="0.01"
                                    value="${balance.toFixed(2)}"
                                    data-max="${balance}">
                          </td>
                      </tr>
                    `;
                    $('#voucherPaymentTableBody').append(row);
                });

                $('#totalPaymentAmount').text('' + totalBalance.toLocaleString('en-IN', {
                    minimumFractionDigits: 2
                }));
            });

            // Payment amount validation
            $(document).on('input', '.payment-amount', function() {
                const input = $(this);
                const max = parseFloat(input.data('max'));
                const value = parseFloat(input.val()) || 0;

                // Validate amount is greater than zero
                if (value <= 0) {
                    input.addClass('is-invalid');
                    if (!input.next('.invalid-feedback').length) {
                        input.after('<div class="invalid-feedback">Amount must be greater than zero</div>');
                    } else {
                        input.next('.invalid-feedback').text('Amount must be greater than zero');
                    }
                }
                // Validate amount doesn't exceed balance
                else if (value > max) {
                    input.addClass('is-invalid');
                    if (!input.next('.invalid-feedback').length) {
                        input.after(
                            `<div class="invalid-feedback">Amount cannot exceed balance (${max.toFixed(2)})</div>`
                        );
                    } else {
                        input.next('.invalid-feedback').text(
                            `Amount cannot exceed balance (${max.toFixed(2)})`);
                    }
                    input.val(max.toFixed(2)); // Correct the value to maximum allowed
                }
                // Valid amount
                else {
                    input.removeClass('is-invalid').addClass('is-valid');
                    input.next('.invalid-feedback').remove();
                }

                // Update total
                let total = 0;
                let hasError = false;

                $('.payment-amount').each(function() {
                    const inputVal = parseFloat($(this).val()) || 0;
                    total += inputVal;
                    if ($(this).hasClass('is-invalid')) {
                        hasError = true;
                    }
                });

                $('#totalPaymentAmount').text('' + total.toLocaleString('en-IN', {
                    minimumFractionDigits: 2
                }));

                // Enable/disable submit button based on validation
                const submitBtn = $('#voucherPaymentModal .btn-success');
                submitBtn.prop('disabled', hasError || total <= 0);
            });

            // Handle bulk memo ID button
            $('#bulk-memo-id-btn').on('click', function() {
                const checkedBoxes = Object.values(selectedRows);

                if (checkedBoxes.length === 0) {
                    alert('Please select good hires (lorry hire) first.');
                    return;
                }

                // Clear previous data
                $('#bulkMemoTableBody').empty();

                checkedBoxes.forEach(function(data) {
                    const lrNumber = data.lr;
                    const lrId = data.lr_id;
                    const freightBookingId = data.freight_booking_id;

                    const row = `
                <tr>
                    <td>
                        <input type="hidden" name="lr_ids[]" value="${lrId}">
                        <input type="hidden" name="freight_booking_ids[]" value="${freightBookingId}">
                        ${lrNumber}
                    </td>
                </tr>
            `;
                    $('#bulkMemoTableBody').append(row);
                });
            });
        });
    </script>

    <style>
        .badge {
            font-size: 0.65em;
        }

        .clickable {
            cursor: pointer;
            color: #0d6efd;
            text-decoration: underline;
        }

        .clickable:hover {
            text-decoration: none;
        }

        .memo-assigned {
            background-color: #fff3cd !important;
        }

        .memo-tooltip {
            position: relative;
            display: inline-block;
            margin-left: 5px;
        }

        .memo-tooltip .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .memo-tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
    </style>

    <script>
        $(document).ready(function() {
            // Initialize tooltips
            $('[data-bs-toggle="tooltip"]').tooltip();

            // DataTable initialization (server-side; rows load via AJAX)
            var table = $('#freightBookingsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('freight_bookings.datatable') }}",
                    data: function(d) {
                        $('#searchForm').serializeArray().forEach(function(field) {
                            if (field.name === 'search') {
                                d.filter_search = field.value;
                            } else {
                                d[field.name] = field.value;
                            }
                        });
                    }
                },
                columns: [{
                        data: 'checkbox',
                        name: 'checkbox',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'gr_date',
                        name: 'gr_date',
                        className: 'text-center'
                    },
                    {
                        data: 'lr_number',
                        name: 'lr_number'
                    },
                    {
                        data: 'customer',
                        name: 'customer',
                        orderable: false
                    },
                    {
                        data: 'transporter',
                        name: 'transporter',
                        orderable: false
                    },
                    {
                        data: 'truck_number',
                        name: 'truck_number',
                        orderable: false
                    },
                    {
                        data: 'route',
                        name: 'route',
                        orderable: false
                    },
                    {
                        data: 'weight',
                        name: 'weight'
                    },
                    {
                        data: 'rate',
                        name: 'rate'
                    },
                    {
                        data: 'total',
                        name: 'total'
                    },
                    {
                        data: 'balance',
                        name: 'balance',
                        className: 'text-end'
                    },
                    {
                        data: 'actions',
                        name: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    }
                ],
                pageLength: 25,
                paging: true,
                info: true,
                searching: false,
                ordering: true,
                theme: 'bootstrap-5',
                responsive: true,
                lengthChange: true,
                lengthMenu: [
                    [10, 25, 50, 100],
                    [10, 25, 50, 100]
                ],
                order: [
                    [1, 'desc']
                ],
                columnDefs: [{
                    orderable: false,
                    targets: [0, 3, 4, 5, 6, -1]
                }],
                language: {
                    emptyTable: `<div class="text-muted">
                                        <i class="bi bi-inbox display-4"></i>
                                        <p class="mt-2">No good hires (lorry hire) found</p>
                                        @if (request('search'))
                                            <p>Try adjusting your search criteria</p>
                                        @endif
                                    </div>`
                }
            });

            // Restore checkbox states after pagination
            table.on('draw', function() {
                $('.row-checkbox').each(function() {
                    const id = $(this).val();
                    if (selectedBookingIds.includes(id)) {
                        $(this).prop('checked', true);
                        // Update selectedRows if not already stored
                        if (!selectedRows[id]) {
                            selectedRows[id] = {
                                id: id,
                                lr: $(this).data('lr'),
                                truck: $(this).data('truck'),
                                balance: parseFloat($(this).data('balance') || 0),
                                transporterId: $(this).data('transporter-id'),
                                customerId: $(this).data('customer-id'),
                                memoId: $(this).data('memo-id'),
                                linkedLrId: $(this).data('linked-lr-id'),
                                voucher_number: $(this).data('voucher_number'),
                                freight_rate: parseFloat($(this).data('freight-rate') || 0),
                                freight_booking_id: $(this).data('freight-booking-id'),
                                lr_id: $(this).data('lr-id')
                            };
                        }
                    } else {
                        $(this).prop('checked', false);
                    }
                });

                updateSelectAllState();
                updatePaymentButtonsVisibility();
            });

            // Apply column search
            $('#filterrow th input.column-search').on('keyup change', function() {
                const index = $(this).closest('th').index();
                table.column(index).search(this.value).draw();
            });

            // Select all checkbox handler
            $('#select-all').on('change', function() {
                const checked = this.checked;
                $('.row-checkbox').prop('checked', checked);
                updatePaymentButtonsVisibility();
            });

            // Individual checkbox handler
            $(document).on('change', '.row-checkbox', function() {
                const allChecked = Object.values(selectedRows).length === $('.row-checkbox').length;
                $('#select-all').prop('checked', allChecked);
                updatePaymentButtonsVisibility();
            });

            // Delete confirmation
            $(document).on('click', '.delete-btn', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to delete this good hire (lorry hire)?')) {
                    $(this).closest('form').submit();
                }
            });

            // Voucher payment button click handler
            $('#voucher-payment-btn').on('click', function(e) {
                e.preventDefault();
                handleVoucherPayment();
            });

            // Bulk payment button click handler
            $('#bulk-payment-btn').on('click', function(e) {
                e.preventDefault();
                handleBulkPayment();
            });
        });
    </script>
    <script>
        // Global variables for plan expiry check
        const planEndDateStr = @json(Auth::user()->company->plan_end_date ?? null);
        const today = new Date();

        function handleVoucherPayment() {
            const checkedBoxes = Object.values(selectedRows);
            const modalBody = document.getElementById('voucherPaymentBody');

            modalBody.innerHTML = '';

            if (checkedBoxes.length === 0) {
                alert("Please select at least one Good Hire (Lorry Hire).");
                return;
            }
            if (planEndDateStr) {
                const planEndDate = new Date(planEndDateStr);
                if (today > planEndDate) {
                    alert(
                        'Your subscription plan has expired. Please renew your subscription to continue using the service.'
                    );
                    // Stop further execution so modal doesn't open
                    return false; // or just return
                }
            }

            // Check if any LR has no voucher number
            const hasNoVoucher = checkedBoxes.some(data => data.voucher_number === '0' || !data.voucher_number);
            if (hasNoVoucher) {
                alert("Please create Good Hire (Lorry Hire) first for selected Good Receipts (Bilty).");
                return;
            }

            // Check for LRs with zero balance and collect their numbers
            const zeroBalanceLRs = checkedBoxes.filter(data => {
                return data.balance <= 0;
            }).map(data => data.lr);

            if (zeroBalanceLRs.length > 0) {
                const message =
                    `The following Good Receipts (Bilty) have zero balance. No payment is required:\n${zeroBalanceLRs.join(', ')}`;
                alert(message);
                return;
            }

            // Check if all selected bookings have zero balance
            const allZeroBalance = checkedBoxes.every(data => {
                return data.balance <= 0;
            });

            if (allZeroBalance) {
                alert("All selected good hires (lorry hire) have zero balance. No payment is required.");
                return;
            }

            // Collect truck numbers
            const truckNumbers = checkedBoxes.map(data => data.truck);
            const firstTruck = truckNumbers[0];
            const allSameTruck = truckNumbers.every(truck => truck === firstTruck);

            if (!allSameTruck) {
                alert(
                    "Selected Good Hires (Lorry Hire) belong to different Truck Numbers. Please select bookings with the same truck."
                );
                return;
            }

            // Header: show truck number once at top-right
            modalBody.innerHTML += `
                  <div class="lr-details-section">
                      <h5>Selected Good Hires (Lorry Hire)</h5>
                      <div class="lr-details-content">
                          <div class="d-flex justify-content-between align-items-center">
                              <div class="badge bg-info fs-6">Truck Number: ${firstTruck || 'N/A'}</div>
                              <div class="badge bg-secondary fs-6">${checkedBoxes.length} booking(s) selected</div>
                          </div>
                      </div>
                  </div>
              `;

            // Collect LR data for allocation
            const lrData = checkedBoxes.map(data => ({
                lrNumber: data.lr,
                balance: data.balance
            }));

            // Add transporter details section
            const firstData = checkedBoxes[0];
            const firstTransporterName = firstData.lr_transporter;
            const firstTransporterMobileNumber = firstData.transporter_mobile_number;
            const firstTransporterShortName = firstData.transporter_short_name;
            const firstTransporterBeneficiaryId = firstData.transporter_beneficiary_id;
            const firstTdsPercentage = firstData.tds_percentage;
            const firstAdvanceAmount = firstData.advance_amount;

            modalBody.innerHTML += `
                  <div class="alert alert-primary mb-3">
                      <h6 class="mb-2"><i class="fas fa-truck"></i> Transporter Details</h6>
                      <div class="row">
                            <div class="col-md-4">
                                <strong>Transporter:</strong> ${firstTransporterName || 'N/A'}
                            </div>
                            <div class="col-md-4">
                                <strong>Mobile:</strong> ${firstTransporterMobileNumber || 'N/A'}
                            </div>
                            <div class="col-md-4">
                                <strong>Short Name:</strong> ${firstTransporterShortName || 'N/A'}
                            </div>
                            <div class="col-md-4">
                                <strong>Beneficiary ID:</strong> ${firstTransporterBeneficiaryId || 'N/A'}
                            </div>
                            <div class="col-md-4">
                              <strong>TDS Percentage:</strong> ${firstTdsPercentage ? firstTdsPercentage + '%' : 'N/A'}
                            </div>
                            <div class="col-md-4">
                                <strong>Truck Number:</strong> ${firstTruck || 'N/A'}
                            </div>
                            <div class="col-md-4 text-danger">
                                <strong>Advance Amount:</strong> ${firstAdvanceAmount || 'N/A'}
                            </div>
                      </div>
                  </div>
              `;

            // Fetch and display payment history first
            fetchPaymentHistory(checkedBoxes.map(data => data.id)).then(paymentHistory => {
                if (paymentHistory && paymentHistory.length > 0) {
                    let paymentRows = '';
                    paymentHistory.forEach(payment => {
                        const pumpName = payment.pump ? (payment.pump.pump_name || 'N/A') : '';
                        const paymentModeDisplay = pumpName
                            ? `${payment.payment_mode}<br><small class="text-muted">${pumpName}</small>`
                            : payment.payment_mode;
                        paymentRows += `
                            <tr>
                                <td>${new Date(payment.date).toLocaleDateString()}</td>
                                <td>${payment.freight_booking_id || 'N/A'}</td>
                                <td>${payment.freight_booking?.lr?.lr_number || 'N/A'}</td>
                                <td>${payment.payment_type}</td>
                                <td>${paymentModeDisplay}</td>
                                <td class="text-end">${parseFloat(payment.amount).toFixed(2)}</td>
                            </tr>
                        `;
                    });

                    modalBody.innerHTML += `
                        <div class="alert alert-info mb-3">
                            <h6 class="mb-2"><i class="fas fa-history"></i> Previous Payments</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>FR No.</th>
                                            <th>LR Number</th>
                                            <th>Type</th>
                                            <th>Mode</th>
                                            <th class="text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${paymentRows}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                }

                // Continue with original layout - show payment form in original SweetAlert style
                const lrRows = checkedBoxes.map((data, index) => {
                    const voucherNumber = data.voucher_number || '-';
                    const freightBookingId = data.freight_booking_id;
                    const lrNumber = data.lr;
                    const balance = data.balance;
                    return `
                        <tr>
                            <td style="text-align: left; padding: 8px; border: 1px solid #ddd;">${freightBookingId || 'N/A'}</td>
                            <td style="text-align: left; padding: 8px; border: 1px solid #ddd;">${lrNumber || 'N/A'}</td>
                            <td style="text-align: center; padding: 8px; border: 1px solid #ddd;">${firstTruck || 'N/A'}</td>
                            <td style="text-align: center; padding: 8px; border: 1px solid #ddd;">${voucherNumber || 'N/A'}</td>
                            <td style="text-align: right; padding: 8px; border: 1px solid #ddd;">${balance.toFixed(2)}</td>
                            <td style="text-align: right; padding: 8px; border: 1px solid #ddd;">
                                <input type="number" step="0.01" min="0" max="${balance}"
                                      style="width: 80px; text-align: right; border: 1px solid #ccc; padding: 2px;"
                                      class="allocated-input" data-lr="${lrNumber}"
                                      onchange="updateTotalPayment()">
                            </td>
                        </tr>
                    `;
                }).join('');

                modalBody.innerHTML += `
                  <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                      <thead>
                          <tr style="background-color: #f8f9fa;">
                              <th style="text-align: left; padding: 8px; border: 1px solid #ddd;">FR NO.</th>
                              <th style="text-align: left; padding: 8px; border: 1px solid #ddd;">LR NUMBER</th>
                              <th style="text-align: center; padding: 8px; border: 1px solid #ddd;">TRUCK NUMBER</th>
                              <th style="text-align: center; padding: 8px; border: 1px solid #ddd;">VOUCHER NUMBER</th>
                              <th style="text-align: right; padding: 8px; border: 1px solid #ddd;">BALANCE AMOUNT</th>
                              <th style="text-align: right; padding: 8px; border: 1px solid #ddd;">PAYMENT AMOUNT</th>
                          </tr>
                      </thead>
                      <tbody>
                          ${lrRows}
                      </tbody>
                  </table>

                  <div style="background-color: #e3f2fd; padding: 10px; margin: 10px 0; border-radius: 4px;">
                      <strong>TOTAL PAYMENT: <span id="totalPaymentAmount">${lrData.reduce((sum, item) => sum + item.balance, 0).toFixed(2)}</span></strong>
                  </div>
                  <div style="margin: 15px 0;">
                      <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                          <div style="flex: 1;">
                              <label style="display: block; margin-bottom: 5px; font-weight: bold;">Payment Type *</label>
                              <select id="paymentType" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
                                  <option value="">Select Payment Type</option>
                                  ${paymentTypes.map(type =>
                                      `<option value="${type.id}">${type.name}</option>`
                                  ).join('')}
                              </select>
                          </div>
                          <div style="flex: 1;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Payment Mode *</label>
                            <select id="paymentMode" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
                                <option value="">Select Payment Mode</option>
                                ${paymentModes.map(mode =>
                                    `<option value="${mode.id}">${mode.name}</option>`
                                ).join('')}
                            </select>
                          </div>
                          <div id="pumpNameContainer" style="display: none ; margin-bottom: 15px; flex: 1;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Pump Name *</label>
                            <select id="pumpName" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                                <option value="">Select Pump</option>
                                @foreach ($pumps as $pump)
                                    <option value="{{ $pump->id }}">{{ $pump->pump_name }} ({{ $pump->contact_name }}) </option>
                                @endforeach
                            </select>
                          </div>
                      </div>
                      <div style="display: flex; gap: 20px; margin-bottom: 15px;">
                         <!-- Payment Date -->
                          <div style="flex: 1;">
                              <label style="display: block; margin-bottom: 5px; font-weight: bold;">Payment Date</label>
                              <input type="date" id="paymentDate" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                          </div>
                          <!-- Reference Transaction ID -->
                          <div style="flex: 1;">
                             <label style="display: block; margin-bottom: 5px; font-weight: bold;">Remarks</label>
                          <textarea id="paymentRemarks" style="width: 100%; height: 60px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" placeholder="Enter any remarks..."></textarea>
                          </div>

                      </div>

                  </div>
                `;

                // Show the modal
                $('#voucherPaymentModal').modal('show');
            }).catch(error => {
                console.error('Error fetching payment history:', error);
                // Continue with original layout even if history fetch fails
                const lrRows = checkedBoxes.map((data, index) => {
                    const voucherNumber = data.voucher_number || '-';
                    const freightBookingId = data.freight_booking_id;
                    const lrNumber = data.lr;
                    const balance = data.balance;
                    return `
                        <tr>
                            <td style="text-align: left; padding: 8px; border: 1px solid #ddd;">${freightBookingId || 'N/A'}</td>
                            <td style="text-align: left; padding: 8px; border: 1px solid #ddd;">${lrNumber || 'N/A'}</td>
                            <td style="text-align: center; padding: 8px; border: 1px solid #ddd;">${firstTruck || 'N/A'}</td>
                            <td style="text-align: center; padding: 8px; border: 1px solid #ddd;">${voucherNumber || 'N/A'}</td>
                            <td style="text-align: right; padding: 8px; border: 1px solid #ddd;">${balance.toFixed(2)}</td>
                            <td style="text-align: right; padding: 8px; border: 1px solid #ddd;">
                                <input type="number" step="0.01" min="0" max="${balance}"
                                      style="width: 80px; text-align: right; border: 1px solid #ccc; padding: 2px;"
                                      class="allocated-input" data-lr="${lrNumber}"
                                      onchange="updateTotalPayment()">
                            </td>
                        </tr>
                    `;
                }).join('');

                modalBody.innerHTML += `
                    <table style="width: 100%; border-collapse: collapse; margin: 15px 0;">
                        <thead>
                            <tr style="background-color: #f8f9fa;">
                                <th style="text-align: left; padding: 8px; border: 1px solid #ddd;">FR NO.</th>
                                <th style="text-align: left; padding: 8px; border: 1px solid #ddd;">LR NUMBER</th>
                                <th style="text-align: center; padding: 8px; border: 1px solid #ddd;">TRUCK NUMBER</th>
                                <th style="text-align: center; padding: 8px; border: 1px solid #ddd;">VOUCHER NUMBER</th>
                                <th style="text-align: right; padding: 8px; border: 1px solid #ddd;">BALANCE AMOUNT</th>
                                <th style="text-align: right; padding: 8px; border: 1px solid #ddd;">PAYMENT AMOUNT</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${lrRows}
                        </tbody>
                    </table>

                    <div style="background-color: #e3f2fd; padding: 10px; margin: 10px 0; border-radius: 4px;">
                        <strong>TOTAL PAYMENT: <span id="totalPaymentAmount">${lrData.reduce((sum, item) => sum + item.balance, 0).toFixed(2)}</span></strong>
                    </div>

                    <div style="margin: 15px 0;">
                        <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                            <div style="flex: 1;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Payment Type *</label>
                                <select id="paymentType" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
                                    <option value="">Select Payment Type</option>
                                    ${paymentTypes.map(type =>
                                        `<option value="${type.id}">${type.name}</option>`
                                    ).join('')}
                                </select>
                            </div>
                            <div style="flex: 1;">
                                <label style="display: block; margin-bottom: 5px; font-weight: bold;">Payment Mode *</label>
                                <select id="paymentMode" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
                                    <option value="">Select Payment Mode</option>
                                    ${paymentModes.map(mode =>
                                        `<option value="${mode.id}">${mode.name}</option>`
                                    ).join('')}
                                </select>
                            </div>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Reference Transaction ID</label>
                            <input type="text" id="referenceId" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" placeholder="Enter reference/transaction ID (optional)">
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Remarks</label>
                            <textarea id="paymentRemarks" style="width: 100%; height: 60px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" placeholder="Enter any remarks..."></textarea>
                        </div>
                    </div>
                `;

                // Show the modal
                updatePaymentButtonsVisibility();
                $('#voucherPaymentModal').modal('show');
            });
        }

        // Individual voucher payment handler
        function handleIndividualVoucherPayment(button) {
            const freightBookingId = button.data('id');
            const lrNumber = button.data('lr');
            const voucherNumber = button.data('voucher_number');
            const balance = parseFloat(button.data('balance'));
            const truckNumber = button.data('truck');

            const modalBody = document.getElementById('individualPaymentBody');
            modalBody.innerHTML = '';

            // Show freight booking details
            modalBody.innerHTML = `
                <div class="lr-details-section">
                    <h5>Good Hire (Lorry Hire) Details</h5>
                    <div class="lr-details-content">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between">
                                    <span><strong>LR Number:</strong></span>
                                    <span>${lrNumber}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span><strong>Voucher Number:</strong></span>
                                    <span>${voucherNumber}</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between">
                                    <span><strong>Truck Number:</strong></span>
                                    <span>${truckNumber}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span><strong>Balance Amount:</strong></span>
                                    <span class="text-success"><strong>${balance.toFixed(2)}</strong></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lr-details-section mt-3">
                    <h5>Payment Details</h5>
                    <div class="lr-details-content">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="individual_payment_type" class="form-label">Payment Type *</label>
                                <select name="payment_type" id="individual_payment_type" class="form-select" required>
                                    <option value="">Select Payment Type</option>
                                    @foreach ($paymentTypes as $paymentType)
                                        <option value="{{ $paymentType->name }}">
                                            {{ $paymentType->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback">Please select a payment type.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="individual_payment_mode" class="form-label">Payment Mode *</label>
                                <select name="payment_mode" id="individual_payment_mode" class="form-select" required>
                                    <option value="">Select Payment Mode</option>
                                    @foreach ($paymentModes as $paymentMode)
                                        <option value="{{ $paymentMode->name }}">
                                            {{ $paymentMode->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback">Please select a payment mode.</div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="individual_reference_transaction_id" class="form-label">Reference Transaction ID</label>
                                <input type="text" class="form-control" name="reference_transaction_id" id="individual_reference_transaction_id">
                            </div>
                            <div class="col-md-6">
                                <label for="individual_remark" class="form-label">Remark</label>
                                <input type="text" class="form-control" name="remark" id="individual_remark">
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="individualTotalAmountInput" class="form-label">Payment Amount *</label>
                                <div class="input-group">
                                    <span class="input-group-text"></span>
                                    <input type="number" step="0.01" class="form-control" name="total_amount" id="individualTotalAmountInput" min="0.01" max="${balance}" required>
                                </div>
                                <div class="invalid-feedback">Please enter a valid payment amount.</div>
                                <small class="text-muted">Amount must be greater than zero and not exceed ${balance.toFixed(2)}</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Store freight booking ID for submission
            $('#individualPaymentModal').data('freight-booking-id', freightBookingId);

            // Show the modal
            $('#individualPaymentModal').modal('show');
        }

        // Handle individual payment submission
        $('#submitIndividualPayment').on('click', function() {
            const freightBookingId = $('#individualPaymentModal').data('freight-booking-id');
            const submitBtn = this;
            const originalBtnText = submitBtn.innerHTML;

            // Validate form
            const paymentType = document.getElementById('individual_payment_type').value;
            const paymentMode = document.getElementById('individual_payment_mode').value;
            const totalAmountInput = document.getElementById('individualTotalAmountInput');
            const totalAmount = totalAmountInput.value;
            const maxAmount = parseFloat(totalAmountInput.getAttribute('max')) || 0;
            const amountValue = parseFloat(totalAmount) || 0;

            if (!paymentType || !paymentMode) {
                alert('Please fill in all required fields.');
                return;
            }

            // Validate amount is greater than zero
            if (amountValue <= 0) {
                alert('Payment amount must be greater than zero.');
                totalAmountInput.focus();
                return;
            }

            // Validate amount doesn't exceed balance
            if (amountValue > maxAmount) {
                alert(
                    `Payment amount (${amountValue.toFixed(2)}) cannot exceed the balance amount (${maxAmount.toFixed(2)}).`
                );
                totalAmountInput.focus();
                return;
            }

            // Show loading state
            submitBtn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Processing...';
            submitBtn.disabled = true;

            // Prepare payment data
            const paymentData = {
                freight_booking_ids: [freightBookingId],
                payment_type: paymentType,
                payment_mode: paymentMode,
                total_amount: parseFloat(totalAmount),
                reference_transaction_id: document.getElementById('individual_reference_transaction_id').value,
                remark: document.getElementById('individual_remark').value,
                allocated_amounts: {
                    [freightBookingId]: parseFloat(totalAmount)
                },
                _token: '{{ csrf_token() }}'
            };

            // Submit payment
            fetch('{{ route('voucher_payments.store') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(paymentData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal
                        $('#individualPaymentModal').modal('hide');
                        let successText = data.message || 'Payment processed successfully!';
                        if (data.adjust_in_advance && data.remaining_advance_balance !== undefined) {
                            const name = data.transporter_name || 'Transporter';
                            const bal = parseFloat(data.remaining_advance_balance).toFixed(2);
                            successText += '\n\nRemaining advance balance (' + name + '):  ' + bal;
                        }
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: successText,
                            confirmButtonColor: '#2FB36F'
                        }).then(() => {
                            $('#freightBookingsTable').DataTable().ajax.reload();
                        });
                    } else {
                        // Show error message
                        alert(data.message || 'Error processing payment. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while processing the payment. Please try again.');
                })
                .finally(() => {
                    // Restore button state
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                });
        });

        // Function to fetch payment history for selected freight bookings
        function fetchPaymentHistory(freightBookingIds) {
            return fetch('{{ route('freight_bookings.payment_history') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        freight_booking_ids: freightBookingIds
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        return data.payments;
                    } else {
                        throw new Error(data.message || 'Failed to fetch payment history');
                    }
                });
        }

        // Function to update total payment amount
        function updateTotalPayment() {
            const allocatedInputs = document.querySelectorAll('.allocated-input');
            let total = 0;
            let hasError = false;

            allocatedInputs.forEach(input => {
                const value = parseFloat(input.value) || 0;
                const maxValue = parseFloat(input.getAttribute('max')) || 0;

                // Validate amount is greater than zero
                if (value <= 0) {
                    input.classList.add('is-invalid');
                    if (!input.nextElementSibling || !input.nextElementSibling.classList.contains(
                            'invalid-feedback')) {
                        const errorMsg = document.createElement('div');
                        errorMsg.className = 'invalid-feedback';
                        errorMsg.textContent = 'Amount must be greater than zero';
                        input.parentNode.insertBefore(errorMsg, input.nextSibling);
                    } else {
                        input.nextElementSibling.textContent = 'Amount must be greater than zero';
                    }
                    hasError = true;
                }
                // Validate amount doesn't exceed balance
                else if (value > maxValue) {
                    input.classList.add('is-invalid');
                    if (!input.nextElementSibling || !input.nextElementSibling.classList.contains(
                            'invalid-feedback')) {
                        const errorMsg = document.createElement('div');
                        errorMsg.className = 'invalid-feedback';
                        errorMsg.textContent = `Amount cannot exceed balance (${maxValue.toFixed(2)})`;
                        input.parentNode.insertBefore(errorMsg, input.nextSibling);
                    } else {
                        input.nextElementSibling.textContent =
                            `Amount cannot exceed balance (${maxValue.toFixed(2)})`;
                    }
                    hasError = true;
                }
                // Valid amount
                else {
                    input.classList.remove('is-invalid');
                    input.classList.add('is-valid');
                    if (input.nextElementSibling && input.nextElementSibling.classList.contains(
                            'invalid-feedback')) {
                        input.nextElementSibling.textContent = '';
                    }
                }

                total += value;
            });

            const totalElement = document.getElementById('totalPaymentAmount');
            if (totalElement) {
                totalElement.textContent = total.toFixed(2);
            }

            // Disable/enable submit button based on validation
            const submitBtn = document.querySelector('#voucherPaymentModal .btn-success');
            if (submitBtn) {
                submitBtn.disabled = hasError || total <= 0;
            }

            return !hasError;
        }

        // Function to submit voucher payment
        function submitVoucherPayment() {
            // Get form values
            const paymentTypeSelect = document.getElementById('paymentType');
            const paymentModeSelect = document.getElementById('paymentMode');
            const pumpId = document.getElementById('pumpName')?.value || null;
            const referenceId = document.getElementById('referenceId')?.value;
            const paymentDate = document.getElementById('paymentDate')?.value;
            const remarks = document.getElementById('paymentRemarks')?.value;

            // Get the selected values
            const paymentTypeValue = paymentTypeSelect?.value || '';
            const paymentModeValue = paymentModeSelect?.value || '';

            // Validate required fields
            if (!paymentTypeValue) {
                alert('Please select a payment type');
                paymentTypeSelect.focus();
                return;
            }

            if (!paymentModeValue) {
                alert('Please select a payment mode');
                paymentModeSelect.focus();
                return;
            }

            // Get the text values for display purposes
            const paymentType = paymentTypeSelect?.options[paymentTypeSelect.selectedIndex]?.text || '';
            const paymentMode = paymentModeSelect?.options[paymentModeSelect.selectedIndex]?.text || '';

            // Get selected freight booking IDs
            const selectedIds = Object.values(selectedRows).map(data => data.id);

            // Get allocated amounts
            const allocatedInputs = document.querySelectorAll('.allocated-input');

            // Validate all payment amounts
            let hasValidationError = false;
            const paymentAmounts = [];

            allocatedInputs.forEach(input => {
                const amount = parseFloat(input.value) || 0;
                const maxAmount = parseFloat(input.getAttribute('max')) || 0;
                const minAmount = parseFloat(input.getAttribute('min')) || 0;
                const lrNumber = input.getAttribute('data-lr') || 'Unknown';

                if (amount < minAmount || amount > maxAmount) {
                    alert(
                        `Error: Payment amount for LR ${lrNumber} (${amount.toFixed(2)}) must be between ${minAmount.toFixed(2)} and ${maxAmount.toFixed(2)}.`
                    );
                    input.focus();
                    hasValidationError = true;
                    return;
                }

                paymentAmounts.push(amount);
            });

            if (hasValidationError) {
                return;
            }

            // Calculate total amount
            const totalAmount = paymentAmounts.reduce((sum, amount) => sum + amount, 0);

            if (totalAmount <= 0) {
                alert('Please enter valid payment amounts');
                return;
            }

            if (!confirm(
                    "Please review all payment details carefully before proceeding.\nOnce the voucher status is marked as PAID, it cannot be edited or changed.\nEnsure the information is correct before saving."
                )) {
                return;
            }

            // Create form data
            const formData = new FormData();
            formData.append('pump_id', pumpId || '');
            // Append payment type and mode IDs for backend processing
            formData.append('payment_type_id', paymentTypeValue);
            formData.append('payment_mode_id', paymentModeValue);
            // Also append text values for display if needed
            formData.append('payment_type', paymentType);
            formData.append('payment_mode', paymentMode);

            formData.append('total_amount', totalAmount.toFixed(2));
            formData.append('date', paymentDate);

            if (referenceId) formData.append('reference_number', referenceId);
            if (remarks) formData.append('notes', remarks);

            // Add booking IDs and payment amounts
            selectedIds.forEach((id, index) => {
                formData.append('booking_ids[]', id);
                formData.append('payment_amounts[]', paymentAmounts[index] || 0);
            });

            // Add CSRF token
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

            // Show loading state
            const submitBtn = document.querySelector('#voucherPaymentModal .btn-success');
            const originalBtnText = submitBtn.innerHTML;
            submitBtn.innerHTML =
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
            submitBtn.disabled = true;

            // Submit the form via AJAX
            fetch('{{ route('freight_bookings.voucher_payment') }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    // Hide modal
                    if (data.success) {
                        let successText = data.message || 'Payment processed successfully!';
                        if (data.adjust_in_advance && data.remaining_advance_balance !== undefined) {
                            const name = data.transporter_name || 'Transporter';
                            const bal = parseFloat(data.remaining_advance_balance).toFixed(2);
                            successText += '\n\nRemaining advance balance (' + name + '):  ' + bal;
                        }
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: successText,
                            confirmButtonColor: '#2FB36F'
                        }).then(() => {
                            $('#voucherPaymentModal').modal('hide');
                            location.reload();
                        });
                    } else {
                        // Check if this is a duplicate warning
                        if (data.warning && data.duplicate_details) {
                            const duplicate = data.duplicate_details;
                            const confirmMessage = `⚠️ DUPLICATE PAYMENT WARNING ⚠️\n\n` +
                                `A similar payment already exists for this booking:\n\n` +
                                `Date: ${duplicate.date}\n` +
                                `Amount: ${duplicate.amount}\n` +
                                `Payment Type: ${duplicate.payment_type}\n` +
                                `Payment Mode: ${duplicate.payment_mode}\n` +
                                `Created: ${duplicate.created_at}\n\n` +
                                `Do you want to proceed with creating this duplicate payment?`;

                            if (confirm(confirmMessage)) {
                                // Add confirm_duplicate flag and resubmit
                                formData.append('confirm_duplicate', '1');

                                // Resubmit the form
                                fetch('{{ route('freight_bookings.voucher_payment') }}', {
                                    method: 'POST',
                                    body: formData,
                                    headers: {
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                    }
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        let successText = data.message || 'Payment processed successfully!';
                                        if (data.adjust_in_advance && data.remaining_advance_balance !== undefined) {
                                            const name = data.transporter_name || 'Transporter';
                                            const bal = parseFloat(data.remaining_advance_balance).toFixed(2);
                                            successText += '\n\nRemaining advance balance (' + name + '):  ' + bal;
                                        }
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Success!',
                                            text: successText,
                                            confirmButtonColor: '#2FB36F'
                                        }).then(() => {
                                            $('#voucherPaymentModal').modal('hide');
                                            location.reload();
                                        });
                                    } else {
                                        alert(data.message || 'Error processing payment. Please try again.');
                                    }
                                })
                                .catch(error => {
                                    console.error('Payment error:', error);
                                    alert('An error occurred while processing the payment. Please try again.');
                                })
                                .finally(() => {
                                    submitBtn.innerHTML = originalBtnText;
                                    submitBtn.disabled = false;
                                });
                                return; // Exit early
                            } else {
                                // User cancelled, restore button
                                submitBtn.innerHTML = originalBtnText;
                                submitBtn.disabled = false;
                                return;
                            }
                        }
                        // Regular error
                        alert(data.message || 'Error processing payment. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Payment error:', error);
                    alert('An error occurred while processing the payment. Please try again.');
                })
                .finally(() => {
                    // Restore button state
                    submitBtn.innerHTML = originalBtnText;
                    submitBtn.disabled = false;
                });
        }
    </script>
    <script>
        document.addEventListener("change", function(e) {
            if (e.target && e.target.id === "paymentMode") {
                const paymentModeDropdown = e.target;
                const selectedText = paymentModeDropdown.options[paymentModeDropdown.selectedIndex].text
                    .toLowerCase();
                const pumpNameContainer = document.getElementById("pumpNameContainer");

                if (selectedText.includes("fuel")) {
                    pumpNameContainer.style.display = "block";
                } else {
                    pumpNameContainer.style.display = "none";
                    document.getElementById("pumpName").value = ""; // reset
                }
            }
        });
    </script>
    <script>
        document.addEventListener('click', function(event) {
            const printButton = event.target.closest('.voucher-print-btn');
            if (!printButton) {
                return;
            }

            event.preventDefault();
            const printUrl = printButton.getAttribute('href');
            window.open(printUrl, '_blank');

            Swal.fire({
                icon: 'question',
                title: 'Print Confirmation',
                text: 'Did you print voucher challan successfully?',
                showCancelButton: true,
                confirmButtonText: 'Yes, Printed',
                cancelButtonText: 'No',
                confirmButtonColor: '#2FB36F',
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                fetch(printButton.dataset.markUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                                'content'),
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            freight_booking_id: printButton.dataset.freightBookingId
                        }),
                    })
                    .then(async (response) => {
                        const data = await response.json();
                        if (!response.ok) {
                            throw new Error(data.message || 'Failed to update print status.');
                        }
                        return data;
                    })
                    .then((data) => {
                        printButton.classList.remove('btn-action-outline-success');
                        printButton.classList.add('btn-action-success');
                        printButton.setAttribute('title',
                            `Last printed: ${data.voucher_last_printed_at}`);
                    })
                    .catch((error) => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Update Failed',
                            text: error.message || 'Could not update print status.',
                        });
                    });
            });
        });
    </script>
@endpush
