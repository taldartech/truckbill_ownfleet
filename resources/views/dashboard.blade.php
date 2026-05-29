@extends('layouts.app')

@section('title', 'Dashboard')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@push('styles')
    <style>
        .content-header {
            padding: 1rem 0;
            margin-bottom: 1.5rem;
        }

        .container-fluid {
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }

        /* Chart container refinement */
        .chart-container {
            position: relative;
            min-height: 350px;
            width: 100%;
        }

        /* Generic card overrides for consistency */
        .card {
            border: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border-radius: 12px;
        }

        /* Table header styling */
        .table thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            background-color: #f8fafc;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
        }
    </style>
@endpush

@section('content')
    <div class="content">
        <div class="container-fluid">
            @php
                $userRole = auth()->user()->userRole?->name ?? 'admin';
            @endphp

            {{-- MASTER ADMIN DASHBOARD --}}
            @if($userRole === 'master_admin' && isset($systemStats))
                @include('dashboard.roles.master_admin')

            {{-- SUPER ADMIN DASHBOARD --}}
            @elseif($userRole === 'super_admin' && isset($adminStats))
                @include('dashboard.roles.super_admin')

            {{-- ADMIN DASHBOARD (admin, or super_admin in company context) --}}
            @elseif((($userRole === 'admin') || ($userRole === 'super_admin' && session('super_admin_company_id'))) && isset($monthlyStats))
                @include('dashboard.roles.admin')

            {{-- MODERATOR DASHBOARD --}}
            @elseif($userRole === 'moderator' && isset($operationalStats, $moderatorStats))
                @include('dashboard.roles.moderator')

            {{-- ACCOUNTANT DASHBOARD --}}
            @elseif($userRole === 'accountant' && isset($financialStats))
                @include('dashboard.roles.accountant')

            {{-- TRANSPORTER DASHBOARD --}}
            @elseif($userRole === 'transporter' && isset($transporterStats))
                @include('dashboard.roles.transporter')

            {{-- CUSTOMER DASHBOARD --}}
            @elseif($userRole === 'customer' && isset($customerStats))
                @include('dashboard.roles.customer')

            {{-- DEFAULT FALLBACK --}}
            @else
                <div class="row">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <h4>Welcome to Dashboard</h4>
                            <p>Dashboard content is being prepared for your role.</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .table th,
        .table td {
            padding: 0.5rem;
        }

        .card-title {
            font-size: 1rem;
            margin-bottom: 0;
        }
    </style>
@endpush

@push('scripts')
    <!-- Additional scripts can be added here if needed -->
@endpush
