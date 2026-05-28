@extends('layouts.simple-app')

@section('title', 'Simple Dashboard')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 class="h4 m-0">Dashboard</h2>
        <a href="{{ route('simple.operations.receipts.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i> Create Good Receipt
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="simple-card p-3">
                <div class="text-muted small">Pending Receipts</div>
                <div class="h4 m-0">{{ $stats['pendingReceipts'] }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="simple-card p-3">
                <div class="text-muted small">Delivered Today</div>
                <div class="h4 m-0">{{ $stats['deliveredToday'] }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="simple-card p-3">
                <div class="text-muted small">Pending Invoices</div>
                <div class="h4 m-0">{{ $stats['pendingInvoice'] }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="simple-card p-3">
                <div class="text-muted small">Last 7 Days Receipts</div>
                <div class="h4 m-0">{{ $stats['recentCount'] }}</div>
            </div>
        </div>
    </div>

    <div class="simple-card p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="h6 m-0">Recent Good Receipts</h3>
            <a href="{{ route('simple.operations.receipts.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>GR Date</th>
                        <th>LR Number</th>
                        <th>Customer</th>
                        <th>Truck</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentReceipts as $receipt)
                        <tr>
                            <td>{{ $receipt->gr_date ? \Carbon\Carbon::parse($receipt->gr_date)->format('d-m-Y') : '-' }}</td>
                            <td>{{ $receipt->lr_number }}</td>
                            <td>{{ $receipt->customer?->name ?? '-' }}</td>
                            <td>{{ $receipt->truck?->truck_number ?? '-' }}</td>
                            <td>{{ $receipt->delivery_status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No receipts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
