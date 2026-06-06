<?php

use App\Http\Controllers\Api\CompanyController as ApiCompanyController;
use App\Http\Controllers\Api\DriverAdvanceController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\ManagedUserController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\UserRoleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompanyController;
use App\Http\Resources\UserResource;
use App\Support\DashboardPreviewData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

$previewData = static function (string $viewName): array {
    if (str_starts_with($viewName, 'dashboard.roles.')) {
        return DashboardPreviewData::defaults();
    }

    if (str_starts_with($viewName, 'trucks.')) {
        return [
            'owners' => collect(),
        ];
    }

    if (str_starts_with($viewName, 'invoices.')) {
        return [
            'totalInvoices' => 0,
            'paidCount' => 0,
            'unpaidCount' => 0,
            'totalAmount' => 0,
        ];
    }

    if (str_starts_with($viewName, 'freight_bookings.')) {
        return [
            'startDate' => '',
            'endDate' => '',
            'paymentTypes' => collect(),
            'paymentModes' => collect(),
            'pumps' => collect(),
        ];
    }

    return [];
};

$resolveView = static function (string $viewName) use ($previewData): ?View {
    if (! view()->exists($viewName)) {
        return null;
    }

    $data = $previewData($viewName);

    if (str_starts_with($viewName, 'dashboard.roles.')) {
        return view('dashboard', $data);
    }

    return view($viewName, $data);
};

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('app.dashboard')
        : redirect()->route('app.login');
});

Route::view('/app/login', 'vue-app')->name('app.login');

Route::get('/app/csrf-token', function (): JsonResponse {
    return response()->json([
        'token' => csrf_token(),
    ]);
})->name('app.csrf-token');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
});

Route::middleware(['auth', 'tenant'])->prefix('app')->name('app.')->group(function () {
    Route::get('/user', function () {
        $user = auth()->user()?->loadMissing(['userRole.permissions', 'tenant']);

        return response()->json([
            'user' => $user !== null ? new UserResource($user) : null,
        ]);
    })->name('user');

    Route::get('/dashboard/stats', [\App\Http\Controllers\Api\FleetDashboardController::class, 'stats'])
        ->name('dashboard.stats');

    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/companies', [ApiCompanyController::class, 'index'])->name('companies.index');
        Route::post('/companies', [ApiCompanyController::class, 'store'])->name('companies.store');
        Route::get('/companies/{company}', [ApiCompanyController::class, 'show'])->name('companies.show');
        Route::put('/companies/{company}', [ApiCompanyController::class, 'update'])->name('companies.update');
        Route::post('/companies/{company}/enter', [ApiCompanyController::class, 'enter'])->name('companies.enter');
        Route::post('/companies/exit', [ApiCompanyController::class, 'exit'])->name('companies.exit');

        Route::get('/user-roles', [UserRoleController::class, 'index'])
            ->middleware('permission:roles.view')
            ->name('user-roles.index');
        Route::get('/user-roles/{user_role}', [UserRoleController::class, 'show'])
            ->middleware('permission:roles.view')
            ->name('user-roles.show');
        Route::put('/user-roles/{user_role}/permissions', [UserRoleController::class, 'syncPermissions'])
            ->middleware('permission:roles.manage')
            ->name('user-roles.sync-permissions');

        Route::get('/permissions', [PermissionController::class, 'index'])
            ->middleware('permission:permissions.view')
            ->name('permissions.index');
        Route::post('/permissions', [PermissionController::class, 'store'])
            ->middleware('permission:permissions.manage')
            ->name('permissions.store');
        Route::get('/permissions/{permission}', [PermissionController::class, 'show'])
            ->middleware('permission:permissions.view')
            ->name('permissions.show');
        Route::put('/permissions/{permission}', [PermissionController::class, 'update'])
            ->middleware('permission:permissions.manage')
            ->name('permissions.update');
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])
            ->middleware('permission:permissions.manage')
            ->name('permissions.destroy');

        Route::get('/managed-users/assignable-roles', [ManagedUserController::class, 'assignableRoles'])
            ->middleware('permission:users.view,super_admins.view')
            ->name('managed-users.assignable-roles');
        Route::get('/managed-users', [ManagedUserController::class, 'index'])
            ->middleware('permission:users.view,super_admins.view')
            ->name('managed-users.index');
        Route::post('/managed-users', [ManagedUserController::class, 'store'])
            ->middleware('permission:users.manage,super_admins.manage')
            ->name('managed-users.store');
        Route::get('/managed-users/{managed_user}', [ManagedUserController::class, 'show'])
            ->middleware('permission:users.view,super_admins.view')
            ->name('managed-users.show');
        Route::put('/managed-users/{managed_user}', [ManagedUserController::class, 'update'])
            ->middleware('permission:users.manage,super_admins.manage')
            ->name('managed-users.update');
        Route::delete('/managed-users/{managed_user}', [ManagedUserController::class, 'destroy'])
            ->middleware('permission:users.manage,super_admins.manage')
            ->name('managed-users.destroy');

        Route::middleware('tenant.context')->group(function () {
            Route::get('/drivers/license-expiring', [DriverController::class, 'licenseExpiring'])
                ->name('drivers.license-expiring');
            Route::get('/drivers', [DriverController::class, 'index'])->name('drivers.index');
            Route::post('/drivers', [DriverController::class, 'store'])->name('drivers.store');
            Route::get('/drivers/{driver}/advance-ledger', [DriverAdvanceController::class, 'ledger'])
                ->name('drivers.advance-ledger');
            Route::post('/drivers/{driver}/advance-entries', [DriverAdvanceController::class, 'store'])
                ->name('drivers.advance-entries.store');
            Route::get('/drivers/{driver}', [DriverController::class, 'show'])->name('drivers.show');
            Route::put('/drivers/{driver}', [DriverController::class, 'update'])->name('drivers.update');
            Route::delete('/drivers/{driver}', [DriverController::class, 'destroy'])->name('drivers.destroy');

            Route::apiResource('challans', \App\Http\Controllers\Api\ChallanController::class);

            Route::get('/vehicles', [\App\Http\Controllers\Api\VehicleController::class, 'index'])->name('vehicles.index');
            Route::post('/vehicles', [\App\Http\Controllers\Api\VehicleController::class, 'store'])->name('vehicles.store');
            Route::get('/vehicles/{vehicle}', [\App\Http\Controllers\Api\VehicleController::class, 'show'])->name('vehicles.show');
            Route::put('/vehicles/{vehicle}', [\App\Http\Controllers\Api\VehicleController::class, 'update'])->name('vehicles.update');
            Route::patch('/vehicles/{vehicle}/status', [\App\Http\Controllers\Api\VehicleController::class, 'toggleStatus'])
                ->name('vehicles.toggle-status');
            Route::delete('/vehicles/{vehicle}', [\App\Http\Controllers\Api\VehicleController::class, 'destroy'])->name('vehicles.destroy');

            Route::get('/vehicle-documents', [\App\Http\Controllers\Api\VehicleDocumentController::class, 'index'])->name('vehicle-documents.index');
            Route::post('/vehicle-documents', [\App\Http\Controllers\Api\VehicleDocumentController::class, 'store'])->name('vehicle-documents.store');
            Route::get('/vehicle-documents/{vehicle_document}/download', [\App\Http\Controllers\Api\VehicleDocumentController::class, 'download'])
                ->name('vehicle-documents.download');
            Route::get('/vehicle-documents/{vehicle_document}/preview', [\App\Http\Controllers\Api\VehicleDocumentController::class, 'preview'])
                ->name('vehicle-documents.preview');
            Route::put('/vehicle-documents/{vehicle_document}', [\App\Http\Controllers\Api\VehicleDocumentController::class, 'update'])->name('vehicle-documents.update');
            Route::delete('/vehicle-documents/{vehicle_document}', [\App\Http\Controllers\Api\VehicleDocumentController::class, 'destroy'])->name('vehicle-documents.destroy');

            Route::apiResource('driver-assignments', \App\Http\Controllers\Api\DriverAssignmentController::class);

            Route::get('/reports/attendance', [\App\Http\Controllers\Api\AttendanceReportController::class, 'index'])
                ->name('reports.attendance.index');
            Route::get('/reports/attendance/export', [\App\Http\Controllers\Api\AttendanceReportController::class, 'export'])
                ->name('reports.attendance.export');

            Route::get('/attendances/month-overview', [\App\Http\Controllers\Api\AttendanceController::class, 'monthOverview'])
                ->name('attendances.month-overview');
            Route::get('/attendances/monthly-wages', [\App\Http\Controllers\Api\AttendanceController::class, 'monthlyWages'])
                ->name('attendances.monthly-wages');
            Route::get('/attendances/settlement-sheet', [\App\Http\Controllers\Api\AttendanceController::class, 'settlementSheet'])
                ->name('attendances.settlement-sheet');
            Route::get('/attendances/settlement-sheet/export', [\App\Http\Controllers\Api\AttendanceController::class, 'exportSettlementSheet'])
                ->name('attendances.settlement-sheet.export');
            Route::patch('/monthly-driver-wages/{monthlyDriverWage}/settlement', [\App\Http\Controllers\Api\AttendanceController::class, 'updateSettlementLine'])
                ->name('monthly-driver-wages.update-settlement');
            Route::patch('/attendances/vehicle-settlement-line', [\App\Http\Controllers\Api\AttendanceController::class, 'updateVehicleSettlementLine'])
                ->name('attendances.vehicle-settlement-line');
            Route::post('/attendances/close-month', [\App\Http\Controllers\Api\AttendanceController::class, 'closeMonth'])
                ->name('attendances.close-month');
            Route::post('/attendances/lock-month', [\App\Http\Controllers\Api\AttendanceController::class, 'lockMonth'])
                ->name('attendances.lock-month');
            Route::post('/attendances/reopen-month', [\App\Http\Controllers\Api\AttendanceController::class, 'reopenMonth'])
                ->name('attendances.reopen-month');
            Route::post('/attendances/unlock-month', [\App\Http\Controllers\Api\AttendanceController::class, 'unlockMonth'])
                ->name('attendances.unlock-month');
            Route::post('/attendances/mark-all-monthly-paid', [\App\Http\Controllers\Api\AttendanceController::class, 'markAllMonthlyWagesPaid'])
                ->name('attendances.mark-all-monthly-paid');
            Route::post('/monthly-driver-wages/{monthlyDriverWage}/mark-paid', [\App\Http\Controllers\Api\AttendanceController::class, 'markMonthlyWagePaid'])
                ->name('monthly-driver-wages.mark-paid');
            Route::post('/attendances/{attendance}/mark-wage-paid', [\App\Http\Controllers\Api\AttendanceController::class, 'markWagePaid'])
                ->name('attendances.mark-wage-paid');
            Route::apiResource('attendances', \App\Http\Controllers\Api\AttendanceController::class);

            Route::get('/trips/next-number', [\App\Http\Controllers\Api\TripController::class, 'nextTripNumber'])
                ->name('trips.next-number');
            Route::get('/vehicles/{vehicle}/primary-driver', [\App\Http\Controllers\Api\TripController::class, 'vehiclePrimaryDriver'])
                ->name('vehicles.primary-driver');
            Route::apiResource('trips', \App\Http\Controllers\Api\TripController::class);
            Route::get('/locations/countries', [\App\Http\Controllers\Api\LocationController::class, 'countries']);
            Route::get('/locations/states', [\App\Http\Controllers\Api\LocationController::class, 'states']);
            Route::get('/locations/cities', [\App\Http\Controllers\Api\LocationController::class, 'cities']);
            Route::apiResource('states', \App\Http\Controllers\Api\StateController::class)->except(['destroy']);
            Route::apiResource('cities', \App\Http\Controllers\Api\CityController::class)->except(['destroy']);
            Route::apiResource('stations', \App\Http\Controllers\Api\StationController::class);

            Route::apiResource('tyre-layouts', \App\Http\Controllers\Api\TyreLayoutController::class);

            Route::post('/tyres/replace', [\App\Http\Controllers\Api\TyreController::class, 'replace'])->name('tyres.replace');
            Route::apiResource('tyres', \App\Http\Controllers\Api\TyreController::class);
            Route::post('/tyres/{tyre}/install', [\App\Http\Controllers\Api\TyreController::class, 'install'])->name('tyres.install');
            Route::post('/tyre-installations/{tyre_installation}/remove', [\App\Http\Controllers\Api\TyreController::class, 'removeInstallation'])
                ->name('tyre-installations.remove');
            Route::post('/tyres/{tyre}/rotate', [\App\Http\Controllers\Api\TyreController::class, 'rotate'])->name('tyres.rotate');
            Route::post('/tyres/{tyre}/retread', [\App\Http\Controllers\Api\TyreController::class, 'retread'])->name('tyres.retread');
            Route::post('/tyres/{tyre}/damage', [\App\Http\Controllers\Api\TyreController::class, 'damage'])->name('tyres.damage');
            Route::post('/tyres/{tyre}/expenses', [\App\Http\Controllers\Api\TyreController::class, 'expense'])->name('tyres.expenses');
            Route::get('/tyre-installations/{tyre_installation}/removal-preview', [\App\Http\Controllers\Api\TyreController::class, 'removalPreview'])
                ->name('tyre-installations.removal-preview');
            Route::post('/tyres/{tyre}/scrap', [\App\Http\Controllers\Api\TyreController::class, 'scrap'])->name('tyres.scrap');
            Route::get('/vehicles/{vehicle}/tyre-positions', [\App\Http\Controllers\Api\TyreController::class, 'vehiclePositions'])
                ->name('vehicles.tyre-positions');

            Route::get('/reports/tyres/life', [\App\Http\Controllers\Api\TyreReportController::class, 'tyreLife'])
                ->name('reports.tyres.life');
            Route::get('/reports/tyres/cost-per-km', [\App\Http\Controllers\Api\TyreReportController::class, 'costPerKm'])
                ->name('reports.tyres.cost-per-km');
            Route::get('/reports/tyres/replacements', [\App\Http\Controllers\Api\TyreReportController::class, 'replacements'])
                ->name('reports.tyres.replacements');
            Route::get('/reports/tyres/retread-history', [\App\Http\Controllers\Api\TyreReportController::class, 'retreadHistory'])
                ->name('reports.tyres.retread-history');
            Route::get('/reports/tyres/vehicle-wise', [\App\Http\Controllers\Api\TyreReportController::class, 'vehicleWiseUsage'])
                ->name('reports.tyres.vehicle-wise');
            Route::get('/reports/tyres/expense-summary', [\App\Http\Controllers\Api\TyreReportController::class, 'expenseSummary'])
                ->name('reports.tyres.expense-summary');

            Route::get('/reports/trips/profitability', [\App\Http\Controllers\Api\TripReportController::class, 'profitability'])
                ->name('reports.trips.profitability');
            Route::get('/reports/trips/vehicle-wise', [\App\Http\Controllers\Api\TripReportController::class, 'vehicleWise'])
                ->name('reports.trips.vehicle-wise');
            Route::get('/reports/trips/driver-wise', [\App\Http\Controllers\Api\TripReportController::class, 'driverWise'])
                ->name('reports.trips.driver-wise');
            Route::get('/reports/trips/empty-km', [\App\Http\Controllers\Api\TripReportController::class, 'emptyKm'])
                ->name('reports.trips.empty-km');
            Route::get('/reports/trips/expense-summary', [\App\Http\Controllers\Api\TripReportController::class, 'expenseSummary'])
                ->name('reports.trips.expense-summary');
            Route::get('/reports/trips/route-profitability', [\App\Http\Controllers\Api\TripReportController::class, 'routeProfitability'])
                ->name('reports.trips.route-profitability');
            Route::get('/reports/trips/pending-freight', [\App\Http\Controllers\Api\TripReportController::class, 'pendingFreight'])
                ->name('reports.trips.pending-freight');
            Route::get('/reports/trips/export', [\App\Http\Controllers\Api\TripReportController::class, 'export'])
                ->name('reports.trips.export');
        });
    });

    Route::view('/dashboard', 'vue-app')->name('dashboard');
    Route::view('/{any}', 'vue-app')->where('any', '.*')->name('spa');
});

Route::get('/robots.txt', function () {
    return response("User-agent: *\nDisallow:\n", 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
});

Route::middleware('auth')->group(function () use ($previewData, $resolveView) {
    Route::get('/dashboard', function () {
        return redirect(auth()->user()->dashboardUrl());
    })->name('dashboard');

    Route::match(['get', 'post'], '/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/password/change', function () {
        return redirect('/ui/auth/forgot-password');
    })->name('password.change');

    Route::post('/password/change', function () {
        return redirect('/ui/auth/forgot-password');
    })->name('password.change.submit');

    (require __DIR__.'/legacy-stubs.php')();

    Route::get('/ui/company', [CompanyController::class, 'index'])->name('company.index');
    Route::get('/company', static fn () => redirect('/ui/company'));
    Route::get('/ui/company/create', [CompanyController::class, 'create'])->name('company.create');
    Route::get('/company/create', static fn () => redirect('/ui/company/create'));
    Route::post('/company', [CompanyController::class, 'store'])->name('company.store');
    Route::get('/company/{company}/edit', [CompanyController::class, 'edit'])->name('company.edit');
    Route::put('/company/{company}', [CompanyController::class, 'update'])->name('company.update');
    Route::patch('/company/{company}/subscription', [CompanyController::class, 'subscription'])->name('company.subscription');
    Route::get('/company/{company}/enter', [CompanyController::class, 'enter'])->name('company.enter');
    Route::delete('/company/{company}', [CompanyController::class, 'destroy'])->name('company.destroy');
    Route::get('/company/{company}/invoice-template-preview/{template}', [CompanyController::class, 'invoiceTemplatePreview'])
        ->name('company.invoice-template-preview');
    Route::get('/company/{company}/lr-copy-template-preview/{template}', [CompanyController::class, 'lrCopyTemplatePreview'])
        ->name('company.lr-copy-template-preview');
    Route::get('/company/{company}/freight-challan-template-preview/{template}', [CompanyController::class, 'freightChallanTemplatePreview'])
        ->name('company.freight-challan-template-preview');

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

    Route::get('/transporters/create', function () use ($resolveView) {
        $view = $resolveView('transporters.create');

        if ($view !== null) {
            return $view;
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

    Route::get('/trucks/create', function () use ($resolveView) {
        $view = $resolveView('trucks.create');

        if ($view !== null) {
            return $view;
        }

        return redirect('/ui/trucks/index');
    })->name('trucks.create');

    Route::get('/drivers/data', function () {
        return response()->json([
            'draw' => (int) request('draw', 1),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ]);
    })->name('drivers.data');

    Route::get('/drivers', function () {
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'draw' => (int) request('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        return redirect('/ui/drivers/index');
    })->name('drivers.index');

    Route::get('/drivers/create', function () {
        if (view()->exists('drivers.create')) {
            return view('ui.preview', [
                'previewView' => 'drivers.create',
                'previewData' => [],
            ]);
        }

        return redirect('/ui/drivers/index');
    })->name('drivers.create');

    Route::post('/drivers', function () {
        return response()->json([
            'success' => true,
            'message' => 'Preview mode only.',
        ]);
    })->name('drivers.store');

    Route::get('/freight_bookings', function () {
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'draw' => (int) request('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        return redirect('/ui/freight_bookings/index');
    })->name('freight_bookings.index');

    Route::get('/freight_bookings/data', function () {
        return response()->json([
            'draw' => (int) request('draw', 1),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ]);
    })->name('freight_bookings.datatable');

    Route::post('/freight_bookings/voucher-payment', function () {
        return response()->json([
            'success' => true,
            'message' => 'Preview mode only.',
        ]);
    })->name('freight_bookings.voucher_payment');

    Route::post('/freight_bookings/store_combined_payment', function () {
        return response()->json([
            'success' => true,
            'message' => 'Preview mode only.',
        ]);
    })->name('freight_bookings.store_combined_payment');

    Route::post('/freight_bookings/bulk-memo-id', function () {
        return response()->json([
            'success' => true,
            'message' => 'Preview mode only.',
        ]);
    })->name('freight_bookings.bulk_memo_id');

    Route::post('/freight_bookings/payment-history', function () {
        return response()->json([
            'success' => true,
            'data' => [],
        ]);
    })->name('freight_bookings.payment_history');

    Route::post('/voucher_payments', function () {
        return response()->json([
            'success' => true,
            'message' => 'Preview mode only.',
        ]);
    })->name('voucher_payments.store');

    Route::get('/invoices', function () {
        if (request()->expectsJson() || request()->ajax()) {
            return response()->json([
                'draw' => (int) request('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        return redirect('/ui/invoices/index');
    })->name('invoices.index');

    Route::get('/invoices/create', function () use ($resolveView) {
        $view = $resolveView('invoices.create');

        if ($view !== null) {
            return $view;
        }

        return redirect('/ui/invoices/index');
    })->name('invoices.create');

    Route::get('/custom-invoices/create', function () {
        return redirect('/ui/invoices/index');
    })->name('custom-invoices.create');

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

    Route::get('/ui/{view?}', function (?string $view = null) use ($resolveView) {
        $candidate = trim((string) $view, '/');
        $normalized = str_replace('/', '.', $candidate);
        $viewsToTry = array_filter([
            $normalized,
            $normalized !== '' ? $normalized.'.index' : null,
            $normalized !== '' ? $normalized.'.create' : null,
        ]);

        foreach ($viewsToTry as $viewName) {
            $resolved = $resolveView($viewName);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        abort(404);
    })->where('view', '.*')->name('ui.preview');

    Route::get('/{path?}', function (?string $path = null) use ($resolveView) {
        $candidate = trim((string) $path, '/');

        if ($candidate === '') {
            return redirect()->route('dashboard');
        }

        $viewName = str_replace('/', '.', $candidate);
        $viewsToTry = [$viewName, $viewName.'.index', $viewName.'.create'];

        foreach ($viewsToTry as $name) {
            $resolved = $resolveView($name);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        abort(404);
    })->where('path', '.*');
});
