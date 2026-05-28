@extends('layouts.simple-app')

@section('title', 'Simple Reports')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 class="h4 m-0">Reports</h2>
        <a href="{{ route('reports.dispatch') }}" class="btn btn-outline-primary">Open Advanced Reports</a>
    </div>

    <div class="simple-card p-3">
        <p class="mb-3">Start with common reports. Use advanced reports when needed.</p>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('reports.dispatch') }}" class="btn btn-primary">Dispatch</a>
            <a href="{{ route('reports.party-ledger') }}" class="btn btn-outline-primary">Party Ledger</a>
            <a href="{{ route('reports.pending-invoice') }}" class="btn btn-outline-primary">Pending Invoice</a>
        </div>
    </div>
@endsection
