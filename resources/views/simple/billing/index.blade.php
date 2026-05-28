@extends('layouts.simple-app')

@section('title', 'Simple Billing')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 class="h4 m-0">Billing</h2>
        <a href="{{ route('invoices.index') }}" class="btn btn-outline-primary">Open Advanced Billing</a>
    </div>

    <div class="simple-card p-3">
        <p class="mb-3">Use these beginner-friendly billing actions first.</p>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('invoices.create') }}" class="btn btn-primary">Create Invoice</a>
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-primary">View Invoices</a>
            <a href="{{ route('voucher_payments.index') }}" class="btn btn-outline-primary">Voucher Payments</a>
        </div>
    </div>
@endsection
