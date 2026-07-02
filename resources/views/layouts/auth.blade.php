<!DOCTYPE html>
<html lang="en-GB">

<head>
    @php
        $canonicalBaseUrl = rtrim((string) config('app.website_url', config('app.url')), '/');
        $canonicalPath = request()->path() === '/' ? '' : '/'.ltrim(request()->path(), '/');
        $canonicalUrl = $canonicalBaseUrl.$canonicalPath;
        $canonicalQuery = request()->getQueryString();

        if (! empty($canonicalQuery)) {
            $canonicalUrl .= '?'.$canonicalQuery;
        }
    @endphp
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'TruckBill')</title>
    @if (config('app.block_search_indexing'))
        <meta name="robots" content="noindex, nofollow, noarchive">
        <link rel="canonical" href="{{ $canonicalUrl }}">
    @endif

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="192x192"  href="{{ asset('favicon/android-icon-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon/favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('favicon/site.webmanifest') }}">
    <!-- End Favicon -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="/css/brand.css">

    <style>
        label {
            font-weight: bold !important;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--brand-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            color: var(--brand-text-primary);
        }

        .auth-card {
            background: #FFFFFF;
            border: 1px solid var(--brand-border);
            border-radius: 24px;
            padding: 3rem;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .brand-logo {
            font-size: 2.5rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 0.5rem;
            letter-spacing: -0.03em;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .page-title {
            text-align: center;
            color: #475569;
            font-size: 0.95rem;
            margin-bottom: 2.5rem;
            font-weight: 500;
        }

        .form-label {
            color: #334155;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.5);
            color: #1e293b;
            padding: 0.85rem 1rem;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.9);
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
            color: #0f172a;
            outline: none;
        }

        .input-group {
            position: relative;
        }

        .input-group-text {
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-right: none;
            color: #475569;
            border-radius: 12px 0 0 12px;
            display: flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
        }

        .btn-auth {
            background: var(--brand-primary);
            border: none;
            border-radius: 12px;
            padding: 0.85rem;
            width: 100%;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            margin-top: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(47, 179, 111, 0.2);
        }

        .btn-auth:hover {
            background: var(--brand-primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(47, 179, 111, 0.25);
        }

        .alert-success {
            background: rgba(240, 253, 244, 0.8);
            border: 1px solid #bbf7d0;
            color: #15803d;
            border-radius: 12px;
            backdrop-filter: blur(5px);
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background: rgba(254, 242, 242, 0.8);
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 12px;
            backdrop-filter: blur(5px);
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            cursor: pointer;
            z-index: 5;
            background: none;
            border: none;
        }

        .form-check-input {
            border-color: #94a3b8;
        }

        .form-check-input:checked {
            background-color: #1e293b;
            border-color: #1e293b;
        }

        .link-primary,
        .back-link {
            color: var(--brand-primary);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }

        .link-primary:hover,
        .back-link:hover {
            color: var(--brand-primary-dark);
            text-decoration: underline;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }
    </style>
    @stack('styles')
</head>

<body>
    <div class="container d-flex justify-content-center">
        <div class="auth-card">
            <div class="logo-section">
                <img src="{{ asset('logo.png') }}" alt="TruckBill Logo" class="brand-image" style="max-height: 120px">
            </div>

            @yield('content')
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById('toggle-icon-' + inputId);

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
    @stack('scripts')
</body>

</html>
