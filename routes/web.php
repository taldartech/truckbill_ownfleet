<?php

use Illuminate\Support\Facades\Route;

$previewData = static function (string $viewName): array {
    if (str_starts_with($viewName, 'dashboard.roles.')) {
        return [
            'monthlyStats' => [
                'total_bookings' => 0,
                'active_trips' => 0,
                'pending_lrs' => 0,
                'delivered_today' => 0,
                'lrs_pending_invoice' => 0,
                'eway_bills_expiring_today' => 0,
                'expired_eway_bills' => 0,
                'outstanding_amount' => 0,
            ],
            'expiredEwayBills' => collect(),
            'pendingInvoices' => collect(),
            'invoicePaymentStatuses' => [
                'pending' => 0,
                'partial' => 0,
                'paid' => 0,
            ],
        ];
    }

    if (str_starts_with($viewName, 'trucks.')) {
        return [
            'owners' => collect(),
        ];
    }

    return [];
};

Route::redirect('/', '/login');
Route::view('/login', 'auth.login')->name('login');
Route::post('/login', function () {
    return redirect('/ui/dashboard/roles/admin');
});
Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
Route::redirect('/dashboard', '/ui/dashboard/roles/admin')->name('dashboard');
Route::post('/logout', function () {
    return redirect('/login');
})->name('logout');
Route::get('/password/change', function () {
    return redirect('/ui/auth/forgot-password');
})->name('password.change');
Route::post('/password/change', function () {
    return redirect('/ui/auth/forgot-password');
})->name('password.change.submit');

Route::get('/transporters', function () {
    if (request()->expectsJson() || request()->ajax()) {
        return response()->json([
            'draw' => (int) request('draw', 1),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ]);
    }

    return redirect('/ui/transporters/index');
})->name('transporters.index');

Route::get('/transporters/create', function () {
    if (view()->exists('transporters.create')) {
        return view('ui.preview', [
            'previewView' => 'transporters.create',
            'previewData' => [],
        ]);
    }

    return redirect('/ui/transporters/index');
})->name('transporters.create');

Route::get('/trucks', function () {
    if (request()->expectsJson() || request()->ajax()) {
        return response()->json([
            'draw' => (int) request('draw', 1),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ]);
    }

    return redirect('/ui/trucks/index');
})->name('trucks.index');

Route::get('/trucks/create', function () {
    if (view()->exists('trucks.create')) {
        return view('ui.preview', [
            'previewView' => 'trucks.create',
            'previewData' => ['owners' => collect()],
        ]);
    }

    return redirect('/ui/trucks/index');
})->name('trucks.create');

Route::post('/assign-owner', function () {
    return response()->json([
        'success' => true,
        'message' => 'Preview mode only.',
    ]);
})->name('trucks.assign-owner');

Route::put('/trucks/{truck}/assign-driver', function () {
    return response()->json([
        'success' => true,
        'message' => 'Preview mode only.',
    ]);
})->name('trucks.assign-driver');

Route::post('/advance-payments', function () {
    return response()->json([
        'message' => 'Preview mode only.',
    ]);
})->name('advance-payments.store');

Route::get('/reports/transporter-master-export', function () {
    return redirect('/ui/transporters/index');
})->name('reports.transporter-master-export');

Route::get('/robots.txt', function () {
    return response("User-agent: *\nDisallow:\n", 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
});

Route::get('/ui/{view?}', function (?string $view = null) use ($previewData) {
    $candidate = trim((string) $view, '/');
    $normalized = str_replace('/', '.', $candidate);
    $viewsToTry = array_filter([
        $normalized,
        $normalized !== '' ? $normalized.'.index' : null,
        $normalized !== '' ? $normalized.'.create' : null,
    ]);

    foreach ($viewsToTry as $viewName) {
        if (view()->exists($viewName)) {
            return view('ui.preview', [
                'previewView' => $viewName,
                'previewData' => $previewData($viewName),
            ]);
        }
    }

    abort(404);
})->where('view', '.*')->name('ui.preview');

Route::get('/{path?}', function (?string $path = null) use ($previewData) {
    $candidate = trim((string) $path, '/');

    if ($candidate === '') {
        return redirect('/login');
    }

    $viewName = str_replace('/', '.', $candidate);
    $viewsToTry = [$viewName, $viewName.'.index', $viewName.'.create'];

    foreach ($viewsToTry as $name) {
        if (view()->exists($name)) {
            return view('ui.preview', [
                'previewView' => $name,
                'previewData' => $previewData($name),
            ]);
        }
    }

    abort(404);
})->where('path', '.*');
