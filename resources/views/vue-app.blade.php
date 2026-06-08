<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ auth()->check() ? 'Dashboard' : 'Login' }} - Truckbill</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32x32.png') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/brand.css') }}">
    <script>
        window.__LOGO_URL__ = @json(asset('logo.png'));
        window.__AUTH_USER__ = @json(
            auth()->check()
                ? (new \App\Http\Resources\UserResource(auth()->user()->loadMissing(['userRole.permissions', 'tenant'])))->resolve()
                : null
        );
        window.__LOGIN_FLASH__ = {
            email: @json(old('email', '')),
            error: @json($errors->first('email')),
            status: @json(session('status')),
            remember: @json((bool) old('remember')),
        };
    </script>
    @if (auth()->check())
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous" />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" crossorigin="anonymous" />
        <link rel="stylesheet" href="/theme/dist/css/adminlte.min.css" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
        @vite(['resources/css/app.css', 'resources/css/vue-app-shell.css', 'resources/js/vue-app/main.js'])
    @else
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        @vite(['resources/css/vue-auth.css', 'resources/js/vue-app/main.js'])
    @endif
</head>
<body @class(['layout-fixed sidebar-expand-lg bg-body-tertiary' => auth()->check()])>
    <div id="app"></div>
    @if (auth()->check())
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
        <script src="/theme/dist/js/adminlte.min.js"></script>
    @endif
</body>
</html>
