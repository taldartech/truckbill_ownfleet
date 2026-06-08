@extends('layouts.app')

@section('content')
    @if (session('success'))
        <div class="alert alert-success mt-2">
            {{ session('success') }}
        </div>
    @endif
    <style>
        /* Consistent styling for view pages */
        .info-card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }

        .info-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
            min-width: 140px;
        }

        .info-value {
            color: #212529;
            text-align: right;
            flex: 1;
        }

        .badge-status {
            font-size: 0.875rem;
            padding: 0.375rem 0.75rem;
        }

        .combined-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            text-align: right;
        }

        .combined-table td {
            text-align: right;
        }

        .combined-table td:last-child {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .combined-table tr td:first-child {
            text-align: left;
        }
    </style>

    @if ($freightBooking->is_cancelled)
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert"
            style="max-width: 1200px; margin: 0 auto;">
            <div class="d-flex align-items-center">
                <i class="fas fa-times-circle fa-2x me-3"></i>
                <div>
                    <h5 class="alert-heading mb-1">
                        <i class="fas fa-exclamation-triangle"></i> CANCELLED FREIGHT BOOKING
                    </h5>
                    <p class="mb-0">This good hire (lorry hire) has been cancelled and cannot be modified. Contact administrator
                        for any changes.</p>
                </div>
            </div>
        </div>
    @endif

    <div class="card {{ $freightBooking->is_cancelled ? 'border-danger' : 'card-info' }} card-outline mb-4"
        style="max-width: 1200px; margin: 0 auto; {{ $freightBooking->is_cancelled ? 'opacity: 0.8;' : '' }}">
        <div
            class="card-header d-flex justify-content-between align-items-center {{ $freightBooking->is_cancelled ? 'bg-danger-subtle' : '' }}">
            <div class="card-title h4">
                Good Hire (Lorry Hire) Details - #{{ $freightBooking->id }}
                @if ($freightBooking->is_cancelled)
                    <span class="badge bg-danger ms-2">
                        <i class="fas fa-times-circle"></i> CANCELLED
                    </span>
                @endif
            </div>
            <div class="card-toolbar">
                @if (!$freightBooking->is_cancelled)
                    @if ($freightBooking->linked_lr_id && isset($combinedTotalBalance) && $combinedTotalBalance > 0 && $linkedFreightBookings->isNotEmpty())
                        <button type="button" class="btn btn-sm btn-success me-2" data-bs-toggle="modal"
                            data-bs-target="#combinedPaymentModal">
                            <i class="bi bi-wallet2"></i> Combined Payment
                        </button>
                    @elseif ($freightBooking->balance_amount > 0 && !$freightBooking->linked_lr_id)
                        <button type="button" class="btn btn-sm btn-success me-2"
                            onclick="openVoucherPaymentModal({{ $freightBooking->id }})">
                            <i class="bi bi-credit-card"></i> Voucher Payment
                        </button>
                    @else
                        <button type="button" class="btn btn-sm btn-secondary me-2" disabled
                            title="{{ $freightBooking->linked_lr_id ? 'Use Combined Payment for linked bookings' : 'No balance amount to pay' }}">
                            <i class="bi bi-credit-card"></i> Voucher Payment
                        </button>
                    @endif
                    <a href="{{ route('freight_bookings.edit', $freightBooking->id) }}{{ \App\Support\ListingQueryString::currentSuffix(\App\Support\ListingQueryString::FREIGHT_BOOKING_KEYS) }}" class="btn btn-sm btn-primary me-2">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                @else
                    <button type="button" class="btn btn-sm btn-secondary me-2" disabled
                        title="Cannot perform actions on cancelled booking">
                        <i class="bi bi-credit-card"></i> Voucher Payment
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary me-2" disabled
                        title="Cannot edit cancelled booking">
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                @endif
                <a href="{{ route('freight_bookings.index') }}{{ \App\Support\ListingQueryString::currentSuffix(\App\Support\ListingQueryString::FREIGHT_BOOKING_KEYS) }}" class="btn btn-sm btn-back">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>
        </div>

        <div class="card-body">
            @if ($freightBooking->lr)
                <div class="row">
                    <div class="col-12">
                        <div class="info-card card">
                            <div class="card-header">
                                <h5 class="mb-0">Related Good Receipt (Bilty) Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="info-row">
                                            <span class="info-label">Customer:</span>
                                            <span
                                                class="info-value">{{ $freightBooking->lr->customer ? $freightBooking->lr->customer->name : 'N/A' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Consignor:</span>
                                            <span
                                                class="info-value">{{ $freightBooking->lr->consignor ? $freightBooking->lr->consignor->name : 'N/A' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Consignee:</span>
                                            <span
                                                class="info-value">{{ $freightBooking->lr->consignee ? $freightBooking->lr->consignee->name : 'N/A' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Reference By:</span>
                                            <span
                                                class="info-value">{{ $freightBooking->lr->reference ? $freightBooking->lr->reference->name : 'N/A' }}</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="info-row">
                                            <span class="info-label">Source City:</span>
                                            <span class="info-value">{{ $freightBooking->lr->source_city ?? 'N/A' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Destination City:</span>
                                            <span
                                                class="info-value">{{ $freightBooking->lr->destination_city ?? 'N/A' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Weight:</span>
                                            <span class="info-value">{{ $freightBooking->lr->weight ?? 'N/A' }} MT</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="info-row">
                                            <span class="info-label">Truck Number:</span>
                                            <span
                                                class="info-value">{{ $freightBooking->lr->truck ? $freightBooking->lr->truck->truck_number : 'N/A' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Transporter:</span>
                                            <span
                                                class="info-value">{{ $freightBooking->lr->transporter ? $freightBooking->lr->transporter->name : 'N/A' }}</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Delivery Status:</span>
                                            <span class="info-value">
                                                @php
                                                    $status = $freightBooking->lr->delivery_status ?? 'Pending';
                                                    $badgeClass = match ($status) {
                                                        'Delivered' => 'bg-success',
                                                        'Cancelled' => 'bg-danger',
                                                        'In-Progress' => 'bg-info',
                                                        default => 'bg-warning',
                                                    };
                                                @endphp
                                                <span
                                                    class="badge {{ $badgeClass }} badge-status">{{ $status }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            <div class="row">
                <!-- Basic Information -->
                <div class="col-md-6">
                    <div class="info-card card">
                        <div class="card-header">
                            <h5 class="mb-0">Basic Information</h5>
                        </div>
                        <div class="card-body">

                            <div class="info-row">
                                <span class="info-label">LR Number:</span>
                                <span
                                    class="info-value">{{ $freightBooking->lr ? $freightBooking->lr->lr_number : 'N/A' }}</span>
                            </div>

                            <div class="info-row">
                                <span class="info-label">GR Date:</span>
                                <span
                                    class="info-value">{{ $freightBooking->lr && $freightBooking->lr->gr_date ? date('d-m-Y', strtotime($freightBooking->lr->gr_date)) : 'N/A' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">E-way Bill:</span>
                                <span class="info-value">{{ $freightBooking->eway_bill_number ?? 'N/A' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">E-way Bill Expiry:</span>
                                <span
                                    class="info-value">{{ $freightBooking->eway_bill_expiry ? date('d-m-Y', strtotime($freightBooking->eway_bill_expiry)) : 'N/A' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Good Type:</span>
                                <span class="info-value">{{ $freightBooking->good_type ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Information -->
                <div class="col-md-6">
                    <div class="info-card card">
                        <div class="card-header">
                            <h5 class="mb-0">Financial Information {!! $freightBooking->linked_lr_id ? '<span class="badge bg-primary">Combined</span>' : '' !!}</h5>
                        </div>
                        <div class="card-body">
                            @if (!$freightBooking->linked_lr_id)
                                <div class="info-row">
                                    <span class="info-label">TR Rate:</span>
                                    <span
                                        class="info-value">{{ number_format($freightBooking->tr_fr_rate ?? 0, 2) }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">TR Amount:</span>
                                    <span
                                        class="info-value">{{ number_format($freightBooking->tr_amount ?? 0, 2) }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Advance:</span>
                                    <span
                                        class="info-value">{{ number_format($freightBooking->advance_amount ?? 0, 2) }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">TDS Amount:</span>
                                    <span
                                        class="info-value">{{ number_format($freightBooking->tds_amount ?? 0, 2) }}</span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Balance:</span>
                                    <span class="info-value">
                                        @php

                                            $badgeClass = 'bg-success';
                                        @endphp
                                        <span
                                            class="badge {{ $badgeClass }} badge-status">{{ number_format($freightBooking->balance_amount ?? 0, 2) }}</span>
                                    </span>
                                </div>
                            @else
                                @php
                                    $frs = $linkedFreightBookings
                                        ->filter(fn($fr) => ! $fr->is_cancelled)
                                        ->values();
                                    $total_tr_amount = $frs->sum('tr_amount');
                                    $total_advance = $frs->sum('advance_amount');
                                    $total_tds_amount = $frs->sum('tds_amount');
                                    $total_balance = $frs->sum('balance_amount');
                                @endphp
                                <table class="table combined-table">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            @foreach ($frs as $fr)
                                                <th>{{ $fr->lr->lr_number }}</th>
                                            @endforeach
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>TR Rate</td>
                                            @foreach ($frs as $fr)
                                                <td>{{ number_format($fr->tr_fr_rate, 2) }}</td>
                                            @endforeach
                                            <td></td>
                                        </tr>
                                        <tr>
                                            <td>TR Amount</td>
                                            @foreach ($frs as $fr)
                                                <td>{{ number_format($fr->tr_amount, 2) }}</td>
                                            @endforeach
                                            <td>{{ number_format($total_tr_amount, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td>Advance</td>
                                            @foreach ($frs as $fr)
                                                <td>{{ number_format($fr->advance_amount, 2) }}</td>
                                            @endforeach
                                            <td>{{ number_format($total_advance, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td>TDS Amount</td>
                                            @foreach ($frs as $fr)
                                                <td>{{ number_format($fr->tds_amount, 2) }}</td>
                                            @endforeach
                                            <td>{{ number_format($total_tds_amount, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td>Balance</td>
                                            @foreach ($frs as $fr)
                                                <td>{{ number_format($fr->balance_amount, 2) }}</td>
                                            @endforeach
                                            <td>{{ number_format($total_balance, 2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Status Information -->
                <div class="col-md-6">
                    <div class="info-card card">
                        <div class="card-header">
                            <h5 class="mb-0">Status Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">Delivered:</span>
                                <span class="info-value">
                                    @if ($freightBooking->is_delivered)
                                        <span class="badge bg-success badge-status">Yes</span>
                                    @else
                                        <span class="badge bg-warning badge-status">No</span>
                                    @endif
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Cancelled:</span>
                                <span class="info-value">
                                    @if ($freightBooking->is_cancelled)
                                        <span class="badge bg-danger badge-status">Yes</span>
                                    @else
                                        <span class="badge bg-secondary badge-status">No</span>
                                    @endif
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">PAN Verified:</span>
                                <span class="info-value">
                                    @if ($freightBooking->pan_verified)
                                        <span class="badge bg-success badge-status">Yes</span>
                                    @else
                                        <span class="badge bg-warning badge-status">No</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="col-md-6">
                    <div class="info-card card">
                        <div class="card-header">
                            <h5 class="mb-0">Additional Information</h5>
                        </div>
                        <div class="card-body">

                            <div class="info-row">
                                <span class="info-label">Remark:</span>
                                <span class="info-value">{{ $freightBooking->remark ?? 'N/A' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Created:</span>
                                <span
                                    class="info-value">{{ $freightBooking->created_at ? $freightBooking->created_at->format('d-m-Y H:i') : 'N/A' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Updated:</span>
                                <span
                                    class="info-value">{{ $freightBooking->updated_at ? $freightBooking->updated_at->format('d-m-Y H:i') : 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Charges Section -->
            @if ($freightBooking->is_delivered)
                <div class="row">
                    <div class="col-12">
                        <div class="info-card card">
                            <div class="card-header">
                                <h5 class="mb-0">Additional Charges</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="d-flex flex-wrap">
                                            <div class="me-4 mb-2">
                                                <span class="info-label">Detention: </span>
                                                <span
                                                    class="fw-bold">{{ number_format($freightBooking->detention ?? 0, 2) }}</span>
                                            </div>
                                            <div class="me-4 mb-2">
                                                <span class="info-label">Deviation: </span>
                                                <span
                                                    class="fw-bold">{{ number_format($freightBooking->deviation ?? 0, 2) }}</span>
                                            </div>
                                            <div class="me-4 mb-2">
                                                <span class="info-label">Misc. Expense: </span>
                                                <span
                                                    class="fw-bold">{{ number_format($freightBooking->misc_expense ?? 0, 2) }}</span>
                                            </div>
                                            <div class="me-4 mb-2">
                                                <span class="info-label">Damage Amount: </span>
                                                <span
                                                    class="fw-bold">{{ number_format($freightBooking->damage_amount ?? 0, 2) }}</span>
                                            </div>
                                            <div class="me-4 mb-2">
                                                <span class="info-label">Shortage Amount: </span>
                                                <span
                                                    class="fw-bold">{{ number_format($freightBooking->shortage_amount ?? 0, 2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>

    <!-- Voucher Payment Modal -->
    <div class="modal fade" id="voucherPaymentModal" tabindex="-1" aria-labelledby="voucherPaymentModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="voucherPaymentModalLabel">Voucher Payment - FR #{{ $freightBooking->id }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Previous Payments Section -->
                    <div class="alert alert-info">
                        <h6><i class="fas fa-history"></i> Previous Payments</h6>
                        <div id="previousPaymentsTable">
                            <div class="text-center">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                Loading payment history...
                            </div>
                        </div>
                    </div>

                    <!-- Transporter Details Section -->
                    <div class="alert alert-secondary">
                        <h6><i class="fas fa-truck"></i> Transporter Details</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Transporter:</strong><br>
                                <span
                                    id="lrTransporter">{{ $freightBooking->lr && $freightBooking->lr->transporter ? $freightBooking->lr->transporter->name : 'N/A' }}</span>
                            </div>
                            <div class="col-md-3">
                                <strong>Mobile:</strong><br>
                                <span
                                    id="transporterMobile">{{ $freightBooking->lr && $freightBooking->lr->transporter ? ($freightBooking->lr->transporter->mobile_number ?? 'N/A') : 'N/A' }}</span>
                            </div>
                            <div class="col-md-3">
                                <strong>Short Name:</strong><br>
                                <span
                                    id="transporterShortName">{{ $freightBooking->lr && $freightBooking->lr->transporter ? ($freightBooking->lr->transporter->short_name ?? 'N/A') : 'N/A' }}</span>
                            </div>
                            <div class="col-md-3">
                                <strong>Beneficiary ID:</strong><br>
                                <span
                                    id="transporterBeneficiaryId">{{ $freightBooking->lr && $freightBooking->lr->transporter ? ($freightBooking->lr->transporter->beneficiary_id ?? 'N/A') : 'N/A' }}</span>
                            </div>
                            <div class="col-md-3">
                                <strong>TDS Percentage:</strong><br>
                                <span
                                    id="tdsPercentage">{{ $freightBooking->transporter_tds_percentage ? $freightBooking->transporter_tds_percentage . '%' : 'N/A' }}</span>
                            </div>
                            <div class="col-md-3">
                                <strong>Truck Number:</strong><br>
                                <span
                                    id="truckNumber">{{ $freightBooking->lr && $freightBooking->lr->truck ? $freightBooking->lr->truck->truck_number : 'N/A' }}</span>
                            </div>
                            @php
                                $advanceAmount = $freightBooking->lr && $freightBooking->lr->transporter ? ($freightBooking->lr->transporter->advance_amount ?? 0) : 0;
                            @endphp
                            @if ($advanceAmount > 0)
                            <div class="col-md-3 text-danger">
                                <strong>Advance Amount:</strong><br>
                                <span id="advanceAmount">{{ number_format($advanceAmount, 2) }}</span>
                            </div>
                            @endif
                        </div>
                    </div>

                    <!-- Payment Allocation Section -->
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-indian-rupee-sign"></i> Payment Allocation</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>FR No.</th>
                                        <th>LR Number</th>
                                        <th>Balance Amount</th>
                                        <th>Payment Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>#{{ $freightBooking->id }}</td>
                                        <td>{{ $freightBooking->lr ? $freightBooking->lr->lr_number : 'N/A' }}</td>
                                        <td>{{ number_format($freightBooking->balance_amount ?? 0, 2) }}</td>
                                        <td>
                                            <input type="number" class="form-control allocated-input"
                                                data-fb-id="{{ $freightBooking->id }}" step="0.01" min="0.01"
                                                max="{{ $freightBooking->balance_amount ?? 0 }}"
                                                oninput="validatePaymentAmount(this)" onchange="updateTotalPayment()">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-2">
                            <strong>Total Payment Amount: <span id="totalPaymentAmount">0.00</span></strong>
                        </div>
                    </div>

                    <!-- Payment Details Section -->
                    <div class="row">
                        <!-- Payment Type -->
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="paymentType" class="form-label">Payment Type *</label>
                                <select class="form-select" id="paymentType" required>
                                    <option value="">Select Payment Type</option>
                                    @foreach ($paymentTypes as $type)
                                        <option value="{{ $type->name }}" data-id="{{ $type->id }}">
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Payment Mode -->
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="paymentMode" class="form-label">Payment Mode *</label>
                                <select class="form-select" id="paymentMode" required>
                                    <option value="">Select Payment Mode</option>
                                    @foreach ($paymentModes as $mode)
                                        <option value="{{ $mode->name }}" data-id="{{ $mode->id }}">
                                            {{ $mode->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Pump Name (hidden by default) -->
                        <div class="col-md-4" id="pumpNameContainer" style="display: none;">
                            <div class="mb-3">
                                <label for="pumpName" class="form-label">Pump Name *</label>
                                <select class="form-select" id="pumpName">
                                    <option value="">Select Pump</option>
                                    @foreach ($pumps as $pump)
                                        <option value="{{ $pump->id }}">{{ $pump->pump_name }}
                                            ({{ $pump->contact_name }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="paymentRemarks" class="form-label">Remarks</label>
                                <textarea class="form-control" id="paymentRemarks" rows="2" placeholder="Enter remarks"></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="paymentDate" class="form-label">Payment Date</label>
                                <input type="date" class="form-control" name="date" id="paymentDate"
                                    value="">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="submitVoucherPayment()">
                        <i class="fas fa-save"></i> Submit Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Function to open voucher payment modal for single freight booking
        function openVoucherPaymentModal(freightBookingId) {
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('voucherPaymentModal'));
            modal.show();

            // Load payment history for this specific freight booking
            loadPaymentHistory([freightBookingId]);
        }

        // Function to load payment history
        function loadPaymentHistory(freightBookingIds) {
            const tableContainer = document.getElementById('previousPaymentsTable');

            // Show loading state
            tableContainer.innerHTML = `
        <div class="text-center">
            <div class="spinner-border spinner-border-sm" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            Loading payment history...
        </div>
    `;

            // Make AJAX request to get payment history
            fetch('/freight_bookings/payment-history', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        freight_booking_ids: freightBookingIds
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.payments && data.payments.length > 0) {
                        // Build table HTML
                        let tableHtml = `
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Payment ID</th>
                                <th>FR No.</th>
                                <th>Voucher Number</th>
                                <th>Amount</th>
                                <th>Payment Type</th>
                                <th>Payment Mode</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

                        data.payments.forEach(payment => {
                            const statusBadge = payment.payment_status === 'paid' ? 'bg-success' :
                                payment.payment_status === 'pending' ? 'bg-warning' :
                                payment.payment_status === 'in progress' ? 'bg-info' : 'bg-secondary';
                            const pumpName = payment.pump ? (payment.pump.pump_name || '') : '';
                            const paymentModeDisplay = pumpName ?
                                `${payment.payment_mode || 'N/A'}<br><small class="text-muted">${pumpName}</small>` :
                                (payment.payment_mode || 'N/A');

                            tableHtml += `
                                <tr>
                                    <td>#${payment.id}</td>
                                    <td>#${payment.freight_booking_id}</td>
                                    <td>${payment.voucher_number || 'N/A'}</td>
                                    <td>${parseFloat(payment.amount || 0).toFixed(2)}</td>
                                    <td>${payment.payment_type || 'N/A'}</td>
                                    <td>${paymentModeDisplay}</td>
                                    <td>${new Date(payment.date).toLocaleDateString('en-IN')}</td>
                                    <td><span class="badge ${statusBadge}">${payment.payment_status || 'N/A'}</span></td>
                                </tr>
                            `;
                        });

                        tableHtml += `
                        </tbody>
                    </table>
                </div>
            `;

                        tableContainer.innerHTML = tableHtml;
                    } else {
                        tableContainer.innerHTML = '<p class="text-muted mb-0">No previous payments found.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading payment history:', error);
                    tableContainer.innerHTML = '<p class="text-danger mb-0">Error loading payment history.</p>';
                });
        }

        // Function to validate payment amount in real-time
        function validatePaymentAmount(input) {
            const value = parseFloat(input.value) || 0;
            const min = parseFloat(input.min) || 0;
            const max = parseFloat(input.max) || 0;

            // Remove any existing validation messages
            const existingFeedback = input.nextElementSibling;
            if (existingFeedback && existingFeedback.classList.contains('invalid-feedback')) {
                existingFeedback.remove();
            }

            // Reset validation classes
            input.classList.remove('is-invalid', 'is-valid');

            // Validate amount is greater than zero
            if (value <= 0) {
                input.classList.add('is-invalid');
                const errorMsg = document.createElement('div');
                errorMsg.className = 'invalid-feedback';
                errorMsg.textContent = 'Amount must be greater than zero';
                input.parentNode.insertBefore(errorMsg, input.nextSibling);
                return false;
            }
            // Validate amount doesn't exceed balance
            else if (value > max) {
                input.classList.add('is-invalid');
                const errorMsg = document.createElement('div');
                errorMsg.className = 'invalid-feedback';
                errorMsg.textContent = `Amount cannot exceed balance (${max.toFixed(2)})`;
                input.parentNode.insertBefore(errorMsg, input.nextSibling);
                return false;
            }
            // Valid amount
            else {
                input.classList.add('is-valid');
                return true;
            }
        }

        // Function to update total payment amount
        function updateTotalPayment() {
            const allocatedInputs = document.querySelectorAll('.allocated-input');
            let total = 0;
            let hasError = false;

            allocatedInputs.forEach(input => {
                // Check if input has validation error
                if (input.classList.contains('is-invalid')) {
                    hasError = true;
                }
                total += parseFloat(input.value) || 0;
            });

            const totalElement = document.getElementById('totalPaymentAmount');
            if (totalElement) {
                totalElement.textContent = total.toFixed(2);
            }

            // Enable/disable submit button based on validation
            const submitBtn = document.querySelector('#voucherPaymentModal .btn-primary');
            if (submitBtn) {
                submitBtn.disabled = hasError || total <= 0;
            }
        }

        // Function to submit voucher payment
        function submitVoucherPayment() {
            // Get form values
            const paymentTypeSelect = document.getElementById('paymentType');
            const paymentModeSelect = document.getElementById('paymentMode');
            const paymentDate = document.getElementById('paymentDate');
            const paymentType = paymentTypeSelect?.value;
            const paymentMode = paymentModeSelect?.value;
            const referenceId = document.getElementById('referenceId')?.value;
            const remarks = document.getElementById('paymentRemarks')?.value;
            const pumpId = document.getElementById('pumpName')?.value || null;

            // Validate required fields
            if (!paymentType) {
                alert('Please select a payment type');
                return;
            }

            if (!paymentMode) {
                alert('Please select a payment mode');
                return;
            }

            // Get payment type and mode IDs from the selected options
            const paymentTypeId = paymentTypeSelect.options[paymentTypeSelect.selectedIndex].dataset.id;
            const paymentModeId = paymentModeSelect.options[paymentModeSelect.selectedIndex].dataset.id;

            // Get freight booking ID and allocated amount
            const allocatedInput = document.querySelector('.allocated-input');
            if (!allocatedInput) {
                alert('Error: Payment input field not found');
                return;
            }

            const freightBookingId = allocatedInput.getAttribute('data-fb-id');
            if (!freightBookingId) {
                alert('Error: Freight booking ID not found');
                return;
            }

            const paymentAmount = parseFloat(allocatedInput.value) || 0;
            const maxBalance = parseFloat(allocatedInput.getAttribute('max')) || 0;

            // Validate payment amount is greater than zero
            if (paymentAmount <= 0) {
                alert('Payment amount must be greater than zero');
                allocatedInput.focus();
                return;
            }

            // Validate payment amount doesn't exceed balance
            if (paymentAmount > maxBalance) {
                alert(
                    `Payment amount (${paymentAmount.toFixed(2)}) cannot exceed the balance amount (${maxBalance.toFixed(2)})`
                );
                allocatedInput.focus();
                return;
            }

            // Calculate total amount
            const totalAmount = paymentAmount;

            // Prepare form data
            const formData = {
                booking_ids: [freightBookingId],
                payment_amounts: [paymentAmount],
                total_amount: totalAmount,
                payment_type_id: paymentTypeId,
                payment_mode_id: paymentModeId,
                payment_type: paymentType,
                payment_mode: paymentMode,
                reference_number: referenceId,
                notes: remarks,
                pump_id: pumpId,
                date: paymentDate.value
            };

            if (!confirm(
                    "Please review all payment details carefully before proceeding.\nOnce the voucher status is marked as PAID, it cannot be edited or changed.\nEnsure the information is correct before saving."
                )) {
                return;
            }
            // Show loading state
            const submitBtn = document.querySelector('#voucherPaymentModal .btn-primary');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;

            // Submit payment
            fetch('/freight_bookings/voucher-payment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(formData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let successText = 'Payment submitted successfully!';
                        if (data.adjust_in_advance && data.remaining_advance_balance !== undefined) {
                            const name = data.transporter_name || 'Transporter';
                            const bal = parseFloat(data.remaining_advance_balance).toFixed(2);
                            successText += '\n\nRemaining advance balance (' + name + '):  ' + bal;
                        }
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: successText,
                                confirmButtonColor: '#2FB36F'
                            }).then(() => {
                                bootstrap.Modal.getInstance(document.getElementById('voucherPaymentModal')).hide();
                                window.location.reload();
                            });
                        } else {
                            alert(successText);
                            bootstrap.Modal.getInstance(document.getElementById('voucherPaymentModal')).hide();
                            window.location.reload();
                        }
                    } else {
                        alert('Error: ' + (data.message || 'Failed to submit payment'));
                    }
                })
                .catch(error => {
                    console.error('Error submitting payment:', error);
                    alert('Error submitting payment. Please try again.');
                })
                .finally(() => {
                    // Restore button state
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
        }
    </script>

    <script>
        function togglePumpName() {
            const paymentModeDropdown = document.getElementById("paymentMode");
            if (!paymentModeDropdown) return;

            const selectedText = paymentModeDropdown.options[paymentModeDropdown.selectedIndex]?.text.toLowerCase() || "";
            const pumpNameContainer = document.getElementById("pumpNameContainer");

            if (selectedText.includes("fuel")) {
                pumpNameContainer.style.display = "block";
            } else {
                pumpNameContainer.style.display = "none";
                document.getElementById("pumpName").value = ""; // reset
            }
        }

        // Run when dropdown changes
        document.addEventListener("change", function(e) {
            if (e.target && e.target.id === "paymentMode") {
                togglePumpName();
            }
        });

        // ✅ Run once on page load (or when modal opens)
        document.addEventListener("DOMContentLoaded", togglePumpName);

        // ✅ If this modal is loaded dynamically via Bootstrap, run on modal show
        $('#voucherPaymentModal').on('shown.bs.modal', function() {
            togglePumpName();
        });
    </script>

    @if ($freightBooking->linked_lr_id && $linkedFreightBookings->isNotEmpty())
    <!-- Combined Payment Modal -->
    <div class="modal fade" id="combinedPaymentModal" tabindex="-1" aria-labelledby="combinedPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="combinedPaymentModalLabel">
                        <i class="bi bi-wallet2"></i> Combined Payment - Linked Bookings
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="combinedPaymentForm" method="POST" action="{{ route('freight_bookings.store_combined_payment') }}">
                        @csrf
                        <div class="alert alert-secondary mb-3">
                            <h6><i class="fas fa-truck"></i> Transporter Details</h6>
                            @php
                                $firstLinked = $linkedFreightBookings->first();
                                $firstLr = $firstLinked->lr ?? null;
                            @endphp
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Transporter:</strong><br>
                                    <span>{{ $firstLr && $firstLr->transporter ? $firstLr->transporter->name : ($firstLinked->transporter_name ?? 'N/A') }}</span>
                                </div>
                                <div class="col-md-2">
                                    <strong>TDS %:</strong><br>
                                    <span>{{ $firstLinked->transporter_tds_percentage ?? 0 }}%</span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Truck:</strong><br>
                                    <span>{{ $firstLr && $firstLr->truck ? $firstLr->truck->truck_number : 'N/A' }}</span>
                                </div>
                                <div class="col-md-2">
                                    <strong>Advance:</strong><br>
                                    <span>{{ $firstLr && $firstLr->transporter ? ($firstLr->transporter->advance_amount ?? 'N/A') : 'N/A' }}</span>
                                </div>
                                <div class="col-md-4 d-flex align-items-center gap-2">
                                    <strong>Total Balance:</strong>
                                    <span class="fw-bold">{{ number_format($combinedTotalBalance, 2) }}</span>
                                    <label class="form-label mb-0 ms-2">Combined Amount *</label>
                                    <input type="number" class="form-control" id="combinedAmountInput" name="combined_amount"
                                        step="0.01" min="0.01" max="{{ $combinedTotalBalance }}"
                                        value="{{ $combinedTotalBalance }}" required style="max-width: 150px;">
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>LR Number</th>
                                        <th>Shortage Amount</th>
                                        <th>Damage Amount</th>
                                        <th>Balance</th>
                                        <th>Allocated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($linkedFreightBookings as $fb)
                                        <tr>
                                            <td>{{ $fb->lr ? $fb->lr->lr_number : 'N/A' }}</td>
                                            <td>{{ number_format($fb->shortage_amount ?? 0, 2) }}</td>
                                            <td>{{ number_format($fb->damage_amount ?? 0, 2) }}</td>
                                            <td>{{ number_format($fb->balance_amount ?? 0, 2) }}</td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm allocated-input"
                                                    name="payment_amounts[]" data-fb-id="{{ $fb->id }}"
                                                    data-balance="{{ $fb->balance_amount ?? 0 }}"
                                                    step="0.01" min="0" max="{{ $fb->balance_amount ?? 0 }}"
                                                    value="{{ $fb->balance_amount ?? 0 }}">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @foreach ($linkedFreightBookings as $fb)
                            <input type="hidden" name="booking_ids[]" value="{{ $fb->id }}">
                        @endforeach
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="combinedPaymentType" class="form-label">Payment Type *</label>
                                <select class="form-select" id="combinedPaymentType" name="payment_type" required>
                                    <option value="">Select Payment Type</option>
                                    @foreach ($paymentTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="combinedPaymentMode" class="form-label">Payment Mode *</label>
                                <select class="form-select" id="combinedPaymentMode" name="payment_mode" required>
                                    <option value="">Select Payment Mode</option>
                                    @foreach ($paymentModes as $mode)
                                        <option value="{{ $mode->id }}">{{ $mode->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4" id="combinedPumpContainer" style="display: none;">
                                <label for="combinedPumpName" class="form-label">Pump Name *</label>
                                <select class="form-select" id="combinedPumpName" name="pump_id">
                                    <option value="">Select Pump</option>
                                    @foreach ($pumps as $pump)
                                        <option value="{{ $pump->id }}">{{ $pump->pump_name }} ({{ $pump->contact_name }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="combinedPaymentDate" class="form-label">Payment Date</label>
                                <input type="date" class="form-control" id="combinedPaymentDate" name="payment_date"
                                    value="{{ date('Y-m-d') }}">
                            </div>
                            <div class="col-md-8">
                                <label for="combinedNotes" class="form-label">Notes</label>
                                <textarea class="form-control" id="combinedNotes" name="notes" rows="2" placeholder="Enter notes..."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" id="combinedPaymentSubmitBtn">
                        <i class="fas fa-save"></i> Submit Combined Payment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const totalBalance = {{ $combinedTotalBalance }};
            const amountInput = document.getElementById('combinedAmountInput');
            const allocatedInputs = document.querySelectorAll('#combinedPaymentForm .allocated-input');

            function allocateCombinedAmount() {
                const totalAmount = parseFloat(amountInput?.value) || 0;
                if (totalAmount <= 0 || totalBalance <= 0) return;

                let totalAlloc = 0;
                const allocs = [];
                allocatedInputs.forEach(input => {
                    const bal = parseFloat(input.dataset.balance) || 0;
                    const pct = totalBalance > 0 ? (bal / totalBalance) : 0;
                    let a = Math.min(totalAmount * pct, bal);
                    allocs.push({ input, a });
                    totalAlloc += a;
                });
                const diff = totalAmount - totalAlloc;
                if (Math.abs(diff) > 0.01 && allocs.length > 0) {
                    for (const item of allocs) {
                        const bal = parseFloat(item.input.dataset.balance) || 0;
                        if (bal - item.a > 0 && diff > 0) {
                            item.a += Math.min(bal - item.a, diff);
                            break;
                        }
                    }
                }
                allocs.forEach(({ input, a }) => { input.value = a.toFixed(2); });
            }

            if (amountInput) {
                amountInput.addEventListener('input', allocateCombinedAmount);
            }

            function toggleCombinedPump() {
                const sel = document.getElementById('combinedPaymentMode');
                const pump = document.getElementById('combinedPumpContainer');
                if (!sel || !pump) return;
                const txt = sel.options[sel.selectedIndex]?.text?.toLowerCase() || '';
                pump.style.display = txt.includes('fuel') ? 'block' : 'none';
                if (!txt.includes('fuel')) document.getElementById('combinedPumpName').value = '';
            }
            document.getElementById('combinedPaymentMode')?.addEventListener('change', toggleCombinedPump);
            document.getElementById('combinedPaymentModal')?.addEventListener('shown.bs.modal', toggleCombinedPump);

            document.getElementById('combinedPaymentSubmitBtn')?.addEventListener('click', function() {
                const btn = this;
                const form = document.getElementById('combinedPaymentForm');
                if (!form) return;
                if (!confirm('Please review all payment details carefully before proceeding. Once the voucher status is marked as PAID, it cannot be edited or changed.')) return;

                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';

                const formData = new FormData(form);
                fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: formData
                })
                .then(async res => {
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || data.success === false) {
                        if (data.warning && data.duplicate_details) {
                            const d = data.duplicate_details;
                            return (typeof Swal !== 'undefined' ? Swal.fire({
                                icon: 'warning',
                                title: 'Duplicate Payment Detected',
                                html: `<p>A similar payment already exists:</p><ul><li>Date: ${d.date}</li><li>Amount: ${d.amount}</li><li>Payment Type: ${d.payment_type}</li><li>Payment Mode: ${d.payment_mode}</li></ul><p>Proceed anyway?</p>`,
                                showCancelButton: true,
                                confirmButtonText: 'Yes, Proceed',
                                cancelButtonText: 'Cancel'
                            }) : Promise.resolve({ isConfirmed: confirm('Duplicate payment detected. Proceed?') })).then(r => {
                                if (r?.isConfirmed) {
                                    formData.append('confirm_duplicate', '1');
                                    return fetch(form.action, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' }, body: formData });
                                }
                                throw new Error('Cancelled');
                            }).then(r => r.json());
                        }
                        throw new Error(data.message || 'Failed to process combined payment');
                    }
                    return data;
                })
                .then(data => {
                    bootstrap.Modal.getInstance(document.getElementById('combinedPaymentModal'))?.hide();
                    let successText = data.message || 'Combined payment processed successfully';
                    if (data.adjust_in_advance && data.remaining_advance_balance !== undefined) {
                        const name = data.transporter_name || 'Transporter';
                        const bal = parseFloat(data.remaining_advance_balance).toFixed(2);
                        successText += '\n\nRemaining advance balance (' + name + '):  ' + bal;
                    }
                    (typeof Swal !== 'undefined' ? Swal.fire({ icon: 'success', title: 'Success', text: successText }) : Promise.resolve())
                        .then(() => { window.location.reload(); });
                })
                .catch(err => {
                    if (err.message !== 'Cancelled') {
                        (typeof Swal !== 'undefined' ? Swal.fire({ icon: 'error', title: 'Error', text: err.message }) : alert(err.message));
                    }
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
            });
        })();
    </script>
    @endif

    <!-- Payment History Section -->
    <div class="card mt-4">
        <div class="card-header">
            <h5>Payment History</h5>
        </div>
        <div class="card-body">
            @if ($payments->count() > 0)
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Voucher</th>
                            <th>Amount</th>
                            <th>Payment Type</th>
                            <th>Payment Mode</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $payment)
                            <tr>
                                <td>
                                    @if ($payment instanceof \App\Models\VoucherLinked)
                                        <span class="badge bg-info">Combined</span>
                                    @else
                                        {{ $payment->voucher_number ?? 'N/A' }}
                                    @endif
                                </td>
                                <td>{{ number_format($payment->amount, 2) }}</td>
                                <td>{{ $payment->payment_type ?? 'N/A' }}</td>
                                <td>
                                    {{ $payment->payment_mode ?? 'N/A' }}
                                    @if ($payment->pump)
                                        <br><small class="text-muted">{{ $payment->pump->pump_name }}</small>
                                    @endif
                                </td>
                                <td>{{ $payment->date ? \Carbon\Carbon::parse($payment->date)->format('d-m-Y') : 'N/A' }}
                                </td>
                                <td>
                                    @if ($payment->payment_status === 'paid')
                                        <span class="badge bg-success">Paid</span>
                                    @elseif($payment->payment_status === 'pending')
                                        <span class="badge bg-warning">Pending</span>
                                    @elseif($payment->payment_status === 'in-progress')
                                        <span class="badge bg-info">In Progress</span>
                                    @else
                                        <span class="badge bg-secondary">Unknown</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="alert alert-info">
                    No payment history found.
                </div>
            @endif
        </div>
    </div>

    <!-- User Information of Creator & Updater -->
    <div class="card mt-4">
        <div class="card-header">
            <h5>User Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="info-card card">
                        <div class="card-header">
                            <h5 class="mb-0">Created By</h5>
                        </div>
                        <div class="card-body">
                            @if ($freightBooking->creator)
                                <p><strong>Name:</strong> {{ $freightBooking->creator->name }}</p>
                                <p><strong>Email:</strong> {{ $freightBooking->creator->email }}</p>
                            @else
                                <p class="text-muted">Creator information not available.</p>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card card">
                        <div class="card-header">
                            <h5 class="mb-0">Last Updated By</h5>
                        </div>
                        <div class="card-body">
                            @if ($freightBooking->updater)
                                <p><strong>Name:</strong> {{ $freightBooking->updater->name }}</p>
                                <p><strong>Email:</strong> {{ $freightBooking->updater->email }}</p>
                            @else
                                <p class="text-muted">Updater information not available.</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
