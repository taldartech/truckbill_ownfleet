<!DOCTYPE html>
<html lang="en-GB">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Simple Portal')</title>
    <link rel="stylesheet" href="{{ versioned_asset('theme/dist/css/adminlte.min.css') }}" />
    <link rel="stylesheet" href="{{ versioned_asset('css/brand.css') }}">
    <link rel="stylesheet" href="{{ versioned_asset('css/buttons.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: var(--brand-bg);
        }

        .simple-shell {
            min-height: 100vh;
        }

        .simple-topbar {
            border-bottom: 1px solid var(--brand-border);
            background: #fff;
        }

        .simple-nav .nav-link {
            color: var(--brand-text-primary);
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
        }

        .simple-nav .nav-link.active {
            background: var(--brand-active-bg);
            color: var(--brand-primary);
            font-weight: 600;
        }

        .simple-card {
            border: 1px solid var(--brand-border);
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 2px 8px rgba(17, 24, 39, 0.04);
        }
    </style>
    @stack('styles')
</head>

<body>
    <div class="simple-shell">
        <header class="simple-topbar py-3 mb-4">
            <div class="container-fluid px-4 d-flex flex-wrap align-items-center gap-3 justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <img src="{{ asset('logo.png') }}" alt="TruckBill" style="height: 34px; width: auto;">
                    <h1 class="h5 m-0">Simple Portal</h1>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('dashboard') }}">Full Portal</a>
                    <a href="{{ route('logout') }}" class="btn btn-sm btn-danger">Sign out</a>
                </div>
            </div>
            <div class="container-fluid px-4 mt-3">
                <nav class="simple-nav nav nav-pills gap-2">
                    <a class="nav-link {{ request()->routeIs('simple.dashboard') ? 'active' : '' }}" href="{{ route('simple.dashboard') }}">Dashboard</a>
                    <a class="nav-link {{ request()->routeIs('simple.operations.*') ? 'active' : '' }}" href="{{ route('simple.operations.receipts.index') }}">Operations</a>
                    <a class="nav-link {{ request()->routeIs('simple.billing.*') ? 'active' : '' }}" href="{{ route('simple.billing.index') }}">Billing</a>
                    <a class="nav-link {{ request()->routeIs('simple.reports.*') ? 'active' : '' }}" href="{{ route('simple.reports.index') }}">Reports</a>
                </nav>
            </div>
        </header>

        <main class="container-fluid px-4 pb-5">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</body>

</html>
