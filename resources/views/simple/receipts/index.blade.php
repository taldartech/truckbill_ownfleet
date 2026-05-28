@extends('layouts.simple-app')

@section('title', 'Simple Operations')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 class="h4 m-0">Operations - Good Receipts</h2>
        <div class="d-flex gap-2">
            <a href="{{ route('simple.operations.receipts.create') }}" class="btn btn-primary">Create</a>
            <a href="{{ route('lorry_receipts.index') }}" class="btn btn-outline-primary">Advanced</a>
        </div>
    </div>

    <div class="simple-card p-3 mb-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label for="search" class="form-label">Search</label>
                <input id="search" type="text" name="search" class="form-control" value="{{ $search }}"
                    placeholder="LR number, invoice number, customer">
            </div>
            <div class="col-md-4">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All</option>
                    @foreach (['Pending', 'Delivered', 'Cancelled', 'In-Progress'] as $statusOption)
                        <option value="{{ $statusOption }}" {{ $status === $statusOption ? 'selected' : '' }}>
                            {{ $statusOption }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </form>
    </div>

    <div class="simple-card p-3">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>GR Date</th>
                        <th>LR Number</th>
                        <th>Customer</th>
                        <th>Route</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($receipts as $receipt)
                        <tr>
                            <td>{{ $receipt->gr_date ? \Carbon\Carbon::parse($receipt->gr_date)->format('d-m-Y') : '-' }}</td>
                            <td>{{ $receipt->lr_number }}</td>
                            <td>{{ $receipt->customer?->name ?? '-' }}</td>
                            <td>{{ $receipt->source_city }} - {{ $receipt->destination_city }}</td>
                            <td>
                                <span class="badge {{ $receipt->delivery_status === 'Delivered' ? 'bg-success' : 'bg-warning text-dark' }}">
                                    {{ $receipt->delivery_status }}
                                </span>
                            </td>
                            <td>{{ number_format((float) $receipt->amount, 2) }}</td>
                            <td class="d-flex gap-1">
                                @if (auth()->user()->canActAsAdmin())
                                    <a href="{{ route('lorry_receipts.edit', $receipt) }}?from_simple=1" class="btn btn-sm btn-outline-primary">Edit</a>
                                @endif
                                <a href="{{ route('lorry_receipts.index') }}" class="btn btn-sm btn-outline-secondary">More</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $receipts->links() }}
        </div>
    </div>
@endsection
