<?php

use App\Http\Controllers\Api\BranchController as ApiBranchController;
use App\Http\Controllers\Api\CompanyController as ApiCompanyController;
use App\Http\Controllers\Api\DriverAdvanceController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\ManagedUserController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\SubscriptionController as ApiSubscriptionController;
use App\Http\Controllers\Api\UserRoleController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\CompanyController;
use App\Http\Resources\UserResource;
use App\Support\DashboardPreviewData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

require __DIR__.'/api-admin.php';

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
    Route::get('/forgot-password', fn () => view('auth.forgot-password'))->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
});

Route::middleware(['auth', 'super_admin'])->prefix('subscription')->name('subscription.')->group(function () {
    Route::get('/', fn () => redirect('/app/subscription'))->name('index');
    Route::get('/manage', fn (\Illuminate\Http\Request $request) => redirect('/app/subscription/manage?'.$request->getQueryString()))->name('show');
    Route::get('/checkout', fn (\Illuminate\Http\Request $request) => redirect('/app/subscription/checkout?'.$request->getQueryString()))->name('checkout.form');
    Route::get('/history', fn (\Illuminate\Http\Request $request) => redirect('/app/subscription/history?'.$request->getQueryString()))->name('history');
});

Route::middleware(['auth', 'tenant', 'branch'])->prefix('app')->name('app.')->group(function () {
    Route::get('/user', function () {
        $user = auth()->user()?->loadMissing(['userRole', 'tenant', 'branch']);

        return response()->json([
            'user' => $user !== null ? new UserResource($user) : null,
        ]);
    })->name('user');

    Route::get('/dashboard/stats', [\App\Http\Controllers\Api\FleetDashboardController::class, 'stats'])
        ->name('dashboard.stats');
    Route::get('/dashboard/compliance-alerts', [\App\Http\Controllers\Api\FleetDashboardController::class, 'complianceAlerts'])
        ->name('dashboard.compliance-alerts');

    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/companies', [ApiCompanyController::class, 'index'])->name('companies.index');
        Route::post('/companies', [ApiCompanyController::class, 'store'])->name('companies.store');

        Route::middleware('super_admin')->prefix('subscription')->name('subscription.')->group(function () {
            Route::get('/', [ApiSubscriptionController::class, 'index'])->name('index');
            Route::get('/manage', [ApiSubscriptionController::class, 'show'])->name('show');
            Route::get('/checkout', [ApiSubscriptionController::class, 'checkoutPage'])->name('checkout.page');
            Route::get('/payments', [ApiSubscriptionController::class, 'history'])->name('history');
            Route::post('/quote', [ApiSubscriptionController::class, 'quote'])->name('quote');
            Route::post('/promo/validate', [ApiSubscriptionController::class, 'validatePromo'])->name('promo.validate');
            Route::post('/checkout', [ApiSubscriptionController::class, 'checkout'])->name('checkout');
            Route::post('/callback', [ApiSubscriptionController::class, 'callback'])->name('callback');
        });
        Route::get('/companies/{company}', [ApiCompanyController::class, 'show'])->name('companies.show');
        Route::put('/companies/{company}', [ApiCompanyController::class, 'update'])->name('companies.update');
        Route::post('/companies/{company}/enter', [ApiCompanyController::class, 'enter'])->name('companies.enter');
        Route::post('/companies/exit', [ApiCompanyController::class, 'exit'])->name('companies.exit');

        Route::post('/branches/{branch}/enter', [ApiBranchController::class, 'enter'])->name('branches.enter');
        Route::get('/branches/{branch}/deletion-impact', [ApiBranchController::class, 'deletionImpact'])->name('branches.deletion-impact');
        Route::post('/branches/switch', [ApiBranchController::class, 'switch'])->name('branches.switch');
        Route::post('/branches/exit', [ApiBranchController::class, 'exit'])->name('branches.exit');

        Route::get('/branches', [ApiBranchController::class, 'index'])->name('branches.index');
        Route::post('/branches', [ApiBranchController::class, 'store'])->name('branches.store');
        Route::get('/branches/{branch}', [ApiBranchController::class, 'show'])->name('branches.show');
        Route::put('/branches/{branch}', [ApiBranchController::class, 'update'])->name('branches.update');
        Route::delete('/branches/{branch}', [ApiBranchController::class, 'destroy'])->name('branches.destroy');

        // Global master data — available without branch selection (e.g. company GST form).
        Route::get('/locations/countries', [\App\Http\Controllers\Api\LocationController::class, 'countries']);
        Route::get('/locations/states', [\App\Http\Controllers\Api\LocationController::class, 'states']);
        Route::get('/locations/cities', [\App\Http\Controllers\Api\LocationController::class, 'cities']);

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

        Route::middleware(['tenant.context', 'subscription.active'])->group(function () {
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
            Route::get('/drivers/bulk/template',[DriverController::class, 'downloadBulkTemplate'])->name('drivers.bulk.template');
            Route::post('/drivers/bulk',[DriverController::class, 'bulkUpload'])->name('drivers.bulk.upload');

            Route::apiResource('challans', \App\Http\Controllers\Api\ChallanController::class);
            Route::apiResource('monthly-expenses', \App\Http\Controllers\Api\MonthlyExpenseController::class);

            Route::get('/maintenance-service-types', [\App\Http\Controllers\Api\MaintenanceServiceTypeController::class, 'index'])
                ->name('maintenance-service-types.index');
            Route::get('/vehicle-maintenances/due', [\App\Http\Controllers\Api\VehicleMaintenanceController::class, 'due'])
                ->name('vehicle-maintenances.due');
            Route::get('/vehicles/{vehicle}/maintenances', [\App\Http\Controllers\Api\VehicleMaintenanceController::class, 'forVehicle'])
                ->name('vehicles.maintenances');
            Route::apiResource('vehicle-maintenances', \App\Http\Controllers\Api\VehicleMaintenanceController::class);

            Route::get('/truck-types', [\App\Http\Controllers\Api\TruckTypeController::class, 'index'])->name('truck-types.index');
            Route::post('/truck-types', [\App\Http\Controllers\Api\TruckTypeController::class, 'store'])->name('truck-types.store');
            Route::get('/truck-types/{truck_type}', [\App\Http\Controllers\Api\TruckTypeController::class, 'show'])->name('truck-types.show');
            Route::put('/truck-types/{truck_type}', [\App\Http\Controllers\Api\TruckTypeController::class, 'update'])->name('truck-types.update');
            Route::delete('/truck-types/{truck_type}', [\App\Http\Controllers\Api\TruckTypeController::class, 'destroy'])->name('truck-types.destroy');

            Route::get('/vehicles', [\App\Http\Controllers\Api\VehicleController::class, 'index'])->name('vehicles.index');
            Route::get('/vehicles/for-trip-selection', [\App\Http\Controllers\Api\VehicleController::class, 'forTripSelection'])
                ->name('vehicles.for-trip-selection');
            Route::post('/vehicles', [\App\Http\Controllers\Api\VehicleController::class, 'store'])->name('vehicles.store');
            Route::get('/vehicles/{vehicle}', [\App\Http\Controllers\Api\VehicleController::class, 'show'])->name('vehicles.show');
            Route::put('/vehicles/{vehicle}', [\App\Http\Controllers\Api\VehicleController::class, 'update'])->name('vehicles.update');
            Route::patch('/vehicles/{vehicle}/status', [\App\Http\Controllers\Api\VehicleController::class, 'toggleStatus'])
                ->name('vehicles.toggle-status');
            Route::get('/vehicles/{vehicle}/primary-assignment', [\App\Http\Controllers\Api\VehicleController::class, 'primaryAssignment'])
                ->name('vehicles.primary-assignment');
            Route::post('/vehicles/{vehicle}/remove-primary-driver', [\App\Http\Controllers\Api\VehicleController::class, 'removePrimaryDriver'])
                ->name('vehicles.remove-primary-driver');
            Route::delete('/vehicles/{vehicle}', [\App\Http\Controllers\Api\VehicleController::class, 'destroy'])->name('vehicles.destroy');

            Route::get('/notifications', [\App\Http\Controllers\Api\AppNotificationController::class, 'index'])
                ->name('notifications.index');
            Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\AppNotificationController::class, 'unreadCount'])
                ->name('notifications.unread-count');
            Route::post('/notifications/read-all', [\App\Http\Controllers\Api\AppNotificationController::class, 'markAllRead'])
                ->name('notifications.read-all');
            Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Api\AppNotificationController::class, 'markRead'])
                ->name('notifications.read');

            Route::get('/document-alert-settings', [\App\Http\Controllers\Api\DocumentAlertSettingController::class, 'show'])
                ->middleware('permission:users.manage')
                ->name('document-alert-settings.show');
            Route::put('/document-alert-settings', [\App\Http\Controllers\Api\DocumentAlertSettingController::class, 'update'])
                ->middleware('permission:users.manage')
                ->name('document-alert-settings.update');

            Route::get('/payroll-settings', [\App\Http\Controllers\Api\PayrollSettingController::class, 'show'])
                ->middleware('permission:wages.manage')
                ->name('payroll-settings.show');
            Route::put('/payroll-settings', [\App\Http\Controllers\Api\PayrollSettingController::class, 'update'])
                ->middleware('permission:wages.manage')
                ->name('payroll-settings.update');

            Route::get('/document-types', [\App\Http\Controllers\Api\DocumentTypeController::class, 'index'])->name('document-types.index');
            Route::post('/document-types', [\App\Http\Controllers\Api\DocumentTypeController::class, 'store'])->name('document-types.store');
            Route::get('/document-types/{document_type}', [\App\Http\Controllers\Api\DocumentTypeController::class, 'show'])->name('document-types.show');
            Route::put('/document-types/{document_type}', [\App\Http\Controllers\Api\DocumentTypeController::class, 'update'])->name('document-types.update');
            Route::delete('/document-types/{document_type}', [\App\Http\Controllers\Api\DocumentTypeController::class, 'destroy'])->name('document-types.destroy');

            Route::get('/goods-types', [\App\Http\Controllers\Api\GoodsTypeController::class, 'index'])->name('goods-types.index');
            Route::get('/goods-types/bulk/template', [\App\Http\Controllers\Api\GoodsTypeController::class,'downloadBulkTemplate',])->name('goods-types.bulk.template');
            Route::post('/goods-types', [\App\Http\Controllers\Api\GoodsTypeController::class, 'store'])->name('goods-types.store');
            Route::post('/goods-types/bulk',[\App\Http\Controllers\Api\GoodsTypeController::class,'bulkUpload',])->name('goods-types.bulk');
            Route::get('/goods-types/{goods_type}', [\App\Http\Controllers\Api\GoodsTypeController::class, 'show'])->name('goods-types.show');
            Route::put('/goods-types/{goods_type}', [\App\Http\Controllers\Api\GoodsTypeController::class, 'update'])->name('goods-types.update');
            Route::delete('/goods-types/{goods_type}', [\App\Http\Controllers\Api\GoodsTypeController::class, 'destroy'])->name('goods-types.destroy');

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

            Route::post('/attendances/bulk-mark', [\App\Http\Controllers\Api\AttendanceController::class, 'bulkMark'])
                ->name('attendances.bulk-mark');
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
            Route::get('/trips/check-window', [\App\Http\Controllers\Api\TripController::class, 'checkTripWindow'])
                ->name('trips.check-window');
            Route::get('/trips/{trip}/archive-impact', [\App\Http\Controllers\Api\TripController::class, 'archiveImpact'])
                ->name('trips.archive-impact');
            Route::get('/trips/{trip}/deletion-impact', [\App\Http\Controllers\Api\TripController::class, 'deletionImpact'])
                ->name('trips.deletion-impact');
            Route::post('/trips/{trip}/archive', [\App\Http\Controllers\Api\TripController::class, 'archive'])
                ->name('trips.archive');
            Route::post('/trips/{trip}/restore', [\App\Http\Controllers\Api\TripController::class, 'restore'])
                ->name('trips.restore')
                ->withTrashed();
            Route::post('/trips/{trip}/invoices/preview', [\App\Http\Controllers\Api\InvoiceController::class, 'previewForTrip'])
                ->name('trips.invoices.preview');
            Route::post('/trips/{trip}/invoices/generate', [\App\Http\Controllers\Api\InvoiceController::class, 'generateForTrip'])
                ->name('trips.invoices.generate');
            Route::get('/trips/{trip}/bilty.pdf', [\App\Http\Controllers\Api\TripController::class, 'biltyPdf'])
                ->name('trips.bilty-pdf');
            Route::get('/vehicles/{vehicle}/primary-driver', [\App\Http\Controllers\Api\TripController::class, 'vehiclePrimaryDriver'])
                ->name('vehicles.primary-driver');
            Route::apiResource('trips', \App\Http\Controllers\Api\TripController::class);

            Route::get('/invoices/{invoice}/pdf', [\App\Http\Controllers\Api\InvoiceController::class, 'pdf'])
                ->name('invoices.pdf');
            Route::post('/invoices/{invoice}/payments', [\App\Http\Controllers\Api\InvoiceController::class, 'storePayment'])
                ->name('invoices.payments.store');
            Route::delete('/invoices/{invoice}/payments/{payment}', [\App\Http\Controllers\Api\InvoiceController::class, 'destroyPayment'])
                ->name('invoices.payments.destroy');
            Route::post('/invoices/{invoice}/delete', [\App\Http\Controllers\Api\InvoiceController::class, 'softDelete'])
                ->name('invoices.soft-delete');
            Route::apiResource('invoices', \App\Http\Controllers\Api\InvoiceController::class)->only(['index', 'show', 'destroy']);

            Route::apiResource('states', \App\Http\Controllers\Api\StateController::class)->except(['destroy']);
            Route::apiResource('cities', \App\Http\Controllers\Api\CityController::class)->except(['destroy']);
            Route::apiResource('stations', \App\Http\Controllers\Api\StationController::class);
            Route::get('/stations/bulk/template',[\App\Http\Controllers\Api\StationController::class, 'downloadBulkTemplate'])->name('stations.bulk.template');
            Route::post('/stations/bulk',[\App\Http\Controllers\Api\StationController::class, 'bulkUpload'])->name('stations.bulk.upload');
            Route::apiResource('brokers', \App\Http\Controllers\Api\BrokerController::class);
            Route::apiResource('customers', \App\Http\Controllers\Api\CustomerController::class);
            Route::get('/customers/bulk/template',[\App\Http\Controllers\Api\CustomerController::class, 'downloadBulkTemplate'])->name('customers.bulk.template');
            Route::post('/customers/bulk',[\App\Http\Controllers\Api\CustomerController::class, 'bulkUpload'])->name('customers.bulk.upload');

            Route::apiResource('tyre-layouts', \App\Http\Controllers\Api\TyreLayoutController::class);

            Route::get('/tyres/alerts', [\App\Http\Controllers\Api\TyreController::class, 'alerts'])->name('tyres.alerts');
            Route::post('/tyres/replace', [\App\Http\Controllers\Api\TyreController::class, 'replace'])->name('tyres.replace');
            Route::apiResource('tyres', \App\Http\Controllers\Api\TyreController::class);
            Route::post('/tyres/{tyre}/install', [\App\Http\Controllers\Api\TyreController::class, 'install'])->name('tyres.install');
            Route::post('/tyre-installations/{tyre_installation}/remove', [\App\Http\Controllers\Api\TyreController::class, 'removeInstallation'])
                ->name('tyre-installations.remove');
            Route::patch('/tyre-installations/{tyre_installation}/stint-km', [\App\Http\Controllers\Api\TyreController::class, 'updateStintKm'])
                ->name('tyre-installations.update-stint-km');
            Route::post('/tyres/{tyre}/rotate', [\App\Http\Controllers\Api\TyreController::class, 'rotate'])->name('tyres.rotate');
            Route::post('/tyres/{tyre}/damage', [\App\Http\Controllers\Api\TyreController::class, 'damage'])->name('tyres.damage');
            Route::post('/tyres/{tyre}/inspections', [\App\Http\Controllers\Api\TyreController::class, 'inspection'])->name('tyres.inspections');
            Route::post('/tyres/{tyre}/expenses', [\App\Http\Controllers\Api\TyreController::class, 'expense'])->name('tyres.expenses');
            Route::get('/tyre-installations/{tyre_installation}/removal-preview', [\App\Http\Controllers\Api\TyreController::class, 'removalPreview'])
                ->name('tyre-installations.removal-preview');
            Route::post('/tyres/{tyre}/scrap', [\App\Http\Controllers\Api\TyreController::class, 'scrap'])->name('tyres.scrap');
            Route::get('/vehicles/{vehicle}/tyre-positions', [\App\Http\Controllers\Api\TyreController::class, 'vehiclePositions'])
                ->name('vehicles.tyre-positions');
            Route::get('/vehicles/{vehicle}/tyre-history', [\App\Http\Controllers\Api\TyreController::class, 'vehicleTyreHistory'])
                ->name('vehicles.tyre-history');
            Route::get('/vehicles/bulk/template',[\App\Http\Controllers\Api\VehicleController::class, 'downloadBulkTemplate'])->name('vehicles.bulk.template');
            Route::post('/vehicles/bulk',[\App\Http\Controllers\Api\VehicleController::class, 'bulkUpload'])->name('vehicles.bulk.upload');

            Route::get('/reports/tyres/life', [\App\Http\Controllers\Api\TyreReportController::class, 'tyreLife'])
                ->name('reports.tyres.life');
            Route::get('/reports/tyres/cost-per-km', [\App\Http\Controllers\Api\TyreReportController::class, 'costPerKm'])
                ->name('reports.tyres.cost-per-km');
            Route::get('/reports/tyres/replacements', [\App\Http\Controllers\Api\TyreReportController::class, 'replacements'])
                ->name('reports.tyres.replacements');
            Route::get('/reports/tyres/vehicle-wise', [\App\Http\Controllers\Api\TyreReportController::class, 'vehicleWiseUsage'])
                ->name('reports.tyres.vehicle-wise');
            Route::get('/reports/tyres/expense-summary', [\App\Http\Controllers\Api\TyreReportController::class, 'expenseSummary'])
                ->name('reports.tyres.expense-summary');
            Route::get('/reports/tyres/export', [\App\Http\Controllers\Api\TyreReportController::class, 'export'])
                ->name('reports.tyres.export');

            Route::get('/reports/maintenance/cost-summary', [\App\Http\Controllers\Api\VehicleMaintenanceReportController::class, 'costSummary'])
                ->name('reports.maintenance.cost-summary');
            Route::get('/reports/maintenance/truck-wise', [\App\Http\Controllers\Api\VehicleMaintenanceReportController::class, 'truckWise'])
                ->name('reports.maintenance.truck-wise');
            Route::get('/reports/maintenance/due-services', [\App\Http\Controllers\Api\VehicleMaintenanceReportController::class, 'dueServices'])
                ->name('reports.maintenance.due-services');
            Route::get('/reports/maintenance/export', [\App\Http\Controllers\Api\VehicleMaintenanceReportController::class, 'export'])
                ->name('reports.maintenance.export');

            Route::get('/reports/trips/profitability', [\App\Http\Controllers\Api\TripReportController::class, 'profitability'])
                ->name('reports.trips.profitability');
            Route::get('/reports/trips/vehicle-wise', [\App\Http\Controllers\Api\TripReportController::class, 'vehicleWise'])
                ->name('reports.trips.vehicle-wise');
            Route::get('/reports/trips/driver-wise', [\App\Http\Controllers\Api\TripReportController::class, 'driverWise'])
                ->name('reports.trips.driver-wise');
            Route::get('/reports/trips/expense-summary', [\App\Http\Controllers\Api\TripReportController::class, 'expenseSummary'])
                ->name('reports.trips.expense-summary');
            Route::get('/reports/trips/route-profitability', [\App\Http\Controllers\Api\TripReportController::class, 'routeProfitability'])
                ->name('reports.trips.route-profitability');
            Route::get('/reports/trips/export', [\App\Http\Controllers\Api\TripReportController::class, 'export'])
                ->name('reports.trips.export');
            Route::get('/reports/fleet-monthly', [\App\Http\Controllers\Api\FleetMonthlyReportController::class, 'show'])
                ->name('reports.fleet-monthly');
            Route::get('/reports/fleet-monthly/export', [\App\Http\Controllers\Api\FleetMonthlyReportController::class, 'export'])
                ->name('reports.fleet-monthly.export');
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
    })->where('path', '^(?!api/admin(?:/|$)).*');
});
