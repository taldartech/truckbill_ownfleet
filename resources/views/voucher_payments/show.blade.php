@extends('layouts.app')

@section('content')
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3>Voucher Details: {{ $voucherNumber }}</h3>
            <div class="d-flex gap-2">
                <a href="{{ route('voucher_payments.index') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <a href="{{ route('voucher_payments.index') }}" class="btn btn-secondary">
                    <i class="bi bi-list-ul"></i> Back to List
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <!-- Voucher Summary Card -->
        <div class="card mb-4">
            <div class="card-header bg-gradient-primary">
                <h5 class="card-title mb-0">Voucher Summary</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Basic Information Section -->
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">Basic Information</h6>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <th style="width: 150px">Voucher Number:</th>
                                <td><strong>{{ $voucherNumber }}</strong></td>
                            </tr>
                            <tr>
                                <th>FR Number:</th>
                                <td>{{ $freightBooking ? '#' . $freightBooking->id : '-' }}</td>
                            </tr>
                            <tr>
                                <th>LR Number:</th>
                                <td>{{ $freightBooking && $freightBooking->lr ? $freightBooking->lr->lr_number : '-' }}</td>
                            </tr>
                            <tr>
                                <th>Company:</th>
                                <td>{{ $freightBooking && $freightBooking->company ? $freightBooking->company->name : '-' }}
                                </td>
                            </tr>
                            <tr>
                                <th>GR Date:</th>
                                <td>{{ $freightBooking && $freightBooking->lr && $freightBooking->lr->gr_date ? date('d-m-Y', strtotime($freightBooking->lr->gr_date)) : date('d-m-Y') }}
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Shipment & Financial Information -->
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2">Shipment & Financial Details</h6>
                        <table class="table table-borderless table-sm">
                            @if ($freightBooking && $freightBooking->lr)
                                <tr>
                                    <th style="width: 150px">Customer:</th>
                                    <td>
                                        @if ($freightBooking->lr->customer)
                                            {{ $freightBooking->lr->customer->name }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Truck Number:</th>
                                    <td>
                                        @if ($freightBooking->lr->truck)
                                            {{ $freightBooking->lr->truck->truck_number }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Route:</th>
                                    <td>
                                        @if (
                                            $freightBooking->lr->consignor &&
                                                $freightBooking->lr->consignor->city &&
                                                $freightBooking->lr->consignee &&
                                                $freightBooking->lr->consignee->city)
                                            {{ $freightBooking->lr->consignor->city->name }} →
                                            {{ $freightBooking->lr->consignee->city->name }}
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                            <tr>
                                <th>Total Amount:</th>
                                <td class="text-success fw-bold">{{ number_format($totalAmount, 2) }}</td>
                            </tr>
                            <tr>
                                <th>Total Payments:</th>
                                <td>{{ $payments->count() }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Financial Information Section -->
                <div class="mt-3">
                    <h6 class="border-bottom pb-2">Financial Breakdown</h6>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="d-flex flex-wrap justify-content-between">
                                <div class="me-4 mb-2">
                                    <span class="text-muted">TR Rate:</span>
                                    <span
                                        class="ms-2">{{ $freightBooking && $freightBooking->tr_fr_rate ? '' . number_format($freightBooking->tr_fr_rate, 2) : '0.00' }}</span>
                                </div>
                                <div class="me-4 mb-2">
                                    <span class="text-muted">TR Amount:</span>
                                    <span
                                        class="ms-2">{{ $freightBooking && $freightBooking->tr_amount ? '' . number_format($freightBooking->tr_amount, 2) : '0.00' }}</span>
                                </div>
                                <div class="me-4 mb-2">
                                    <span class="text-muted">Advance:</span>
                                    <span
                                        class="ms-2">{{ $freightBooking && $freightBooking->advance_amount ? '' . number_format($freightBooking->advance_amount, 2) : '0.00' }}</span>
                                </div>
                                <div class="me-4 mb-2">
                                    <span class="text-muted">Balance:</span>
                                    <span
                                        class="ms-2 fw-bold">{{ $freightBooking && $freightBooking->balance_amount ? '' . number_format($freightBooking->balance_amount, 2) : '0.00' }}</span>
                                </div>
                                <div class="mb-2">
                                    <span class="text-muted">TDS Amount:</span>
                                    <span
                                        class="ms-2">{{ $freightBooking && $freightBooking->tds_amount ? '' . number_format($freightBooking->tds_amount, 2) : '0.00' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payments List Card -->
        <div class="card">
            <div class="card-header bg-gradient-primary">
                <h5 class="card-title mb-0">Payment History</h5>
            </div>
            <div class="card-body" style="overflow-x: auto;">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th style="width: 80px">ID</th>
                            <th>Date</th>
                            <th>Payment Type</th>
                            <th>Payment Mode</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Remark</th>
                            <th>Created By</th>
                            <th>Last Updated By</th>
                            @if (auth()->check() && auth()->user()->canActAsAdmin())
                                <th>Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            <tr class="align-middle">
                                <td>{{ $payment->id }}</td>
                                <td>{{ $payment->date }}</td>
                                <td>{{ ucfirst($payment->payment_type) }}</td>
                                <td>
                                    {{ ucfirst(str_replace('_', ' ', $payment->payment_mode)) }}
                                    @if ($payment->pump)
                                        <br><small class="text-muted">{{ $payment->pump->pump_name }}</small>
                                    @endif
                                </td>
                                <td class="text-success fw-bold">{{ number_format($payment->amount, 2) }}</td>
                                <td>{!! $payment->getPaymentStatusBadge() !!}</td>
                                <td>{!! $payment->remark ?? '-' !!}</td>
                                <td>
                                    @if ($payment->created_by)
                                        {{ $payment->creator->name }}
                                        <br>
                                        ({{ $payment->creator->branch->branch_name ?? '' }})
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($payment->updated_by)
                                        {{ $payment->updater->name }}
                                        <br>
                                        ({{ $payment->updater->branch->branch_name ?? '' }})
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                @if (auth()->check() && auth()->user()->canActAsAdmin())
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('voucher_payments.edit', $payment) }}?lorry_receipt_id={{ $payment->freight_booking ? $payment->freight_booking->lr_id : '' }}&freight_booking_id={{ $payment->freight_booking_id }}"
                                                class="btn btn-sm btn-brand-primary" data-bs-toggle="tooltip" data-bs-placement="top"
                                                title="Edit Payment">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="{{ route('voucher_payments.destroy', $payment) }}" method="POST"
                                                style="display:inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Delete Payment"
                                                    onclick="return confirm('Are you sure you want to delete this payment?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center">
                                    <div class="empty-state">
                                        <i class="bi bi-box"></i>
                                        <p class="mt-2">No payment records found.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
