<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrokerController;
use App\Http\Controllers\Api\ChallanController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\ComplianceController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DriverAdvanceController;
use App\Http\Controllers\Api\DriverAssignmentController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\FleetMonthlyReportController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MaintenanceServiceTypeController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PayrollSettingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StateController;
use App\Http\Controllers\Api\StationController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\TripReportController;
use App\Http\Controllers\Api\TyreController;
use App\Http\Controllers\Api\TyreLayoutController;
use App\Http\Controllers\Api\TyreReportController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VehicleDocumentController;
use App\Http\Controllers\Api\VehicleMaintenanceController;
use App\Http\Controllers\Api\VehicleMaintenanceReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('onboarding')
    ->middleware(['onboarding.signature'])
    ->group(function (): void {
        Route::post('create-user', [OnboardingController::class, 'createUser']);
        Route::post('update-user', [OnboardingController::class, 'updateUser']);
        Route::get('status', [OnboardingController::class, 'status']);
    });

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('locations/countries', [LocationController::class, 'countries']);
    Route::get('locations/states', [LocationController::class, 'states']);
    Route::get('locations/cities', [LocationController::class, 'cities']);
});

Route::middleware(['auth:sanctum', 'tenant', 'branch', 'tenant.context'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('vehicles/for-trip-selection', [VehicleController::class, 'forTripSelection']);
    Route::apiResource('vehicles', VehicleController::class);
    Route::patch('vehicles/{vehicle}/status', [VehicleController::class, 'toggleStatus']);
    Route::get('vehicles/{vehicle}/primary-assignment', [VehicleController::class, 'primaryAssignment']);
    Route::post('vehicles/{vehicle}/remove-primary-driver', [VehicleController::class, 'removePrimaryDriver']);
    Route::get('drivers/license-expiring', [DriverController::class, 'licenseExpiring']);
    Route::get('drivers/{driver}/advance-ledger', [DriverAdvanceController::class, 'ledger']);
    Route::post('drivers/{driver}/advance-entries', [DriverAdvanceController::class, 'store']);
    Route::apiResource('drivers', DriverController::class);
    Route::apiResource('driver-assignments', DriverAssignmentController::class);
    Route::post('attendances/bulk-mark', [AttendanceController::class, 'bulkMark']);
    Route::get('attendances/month-overview', [AttendanceController::class, 'monthOverview']);
    Route::get('attendances/monthly-wages', [AttendanceController::class, 'monthlyWages']);
    Route::get('payroll-settings', [PayrollSettingController::class, 'show']);
    Route::put('payroll-settings', [PayrollSettingController::class, 'update']);
    Route::get('attendances/settlement-sheet', [AttendanceController::class, 'settlementSheet']);
    Route::get('attendances/settlement-sheet/export', [AttendanceController::class, 'exportSettlementSheet']);
    Route::patch('monthly-driver-wages/{monthlyDriverWage}/settlement', [AttendanceController::class, 'updateSettlementLine']);
    Route::patch('attendances/vehicle-settlement-line', [AttendanceController::class, 'updateVehicleSettlementLine']);
    Route::post('attendances/close-month', [AttendanceController::class, 'closeMonth']);
    Route::post('attendances/lock-month', [AttendanceController::class, 'lockMonth']);
    Route::post('attendances/reopen-month', [AttendanceController::class, 'reopenMonth']);
    Route::post('attendances/unlock-month', [AttendanceController::class, 'unlockMonth']);
    Route::post('attendances/mark-all-monthly-paid', [AttendanceController::class, 'markAllMonthlyWagesPaid']);
    Route::post('attendances/{attendance}/mark-wage-paid', [AttendanceController::class, 'markWagePaid']);
    Route::post('monthly-driver-wages/{monthlyDriverWage}/mark-paid', [AttendanceController::class, 'markMonthlyWagePaid']);
    Route::apiResource('attendances', AttendanceController::class);
    Route::get('trips/next-number', [TripController::class, 'nextTripNumber']);
    Route::get('trips/check-window', [TripController::class, 'checkTripWindow']);
    Route::get('trips/{trip}/archive-impact', [TripController::class, 'archiveImpact']);
    Route::get('trips/{trip}/deletion-impact', [TripController::class, 'deletionImpact']);
    Route::post('trips/{trip}/archive', [TripController::class, 'archive']);
    Route::post('trips/{trip}/invoices/preview', [InvoiceController::class, 'previewForTrip']);
    Route::post('trips/{trip}/invoices/generate', [InvoiceController::class, 'generateForTrip']);
    Route::get('vehicles/{vehicle}/primary-driver', [TripController::class, 'vehiclePrimaryDriver']);
    Route::apiResource('trips', TripController::class);

    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
    Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'storePayment']);
    Route::delete('invoices/{invoice}/payments/{payment}', [InvoiceController::class, 'destroyPayment']);
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show', 'destroy']);

    Route::apiResource('states', StateController::class)->except(['destroy']);
    Route::apiResource('cities', CityController::class)->except(['destroy']);
    Route::apiResource('stations', StationController::class);
    Route::apiResource('brokers', BrokerController::class);
    Route::apiResource('customers', CustomerController::class);
    Route::apiResource('tyre-layouts', TyreLayoutController::class);
    Route::get('tyres/alerts', [TyreController::class, 'alerts']);
    Route::post('tyres/replace', [TyreController::class, 'replace']);
    Route::apiResource('tyres', TyreController::class);
    Route::post('tyres/{tyre}/install', [TyreController::class, 'install']);
    Route::post('tyre-installations/{tyre_installation}/remove', [TyreController::class, 'removeInstallation']);
    Route::patch('tyre-installations/{tyre_installation}/stint-km', [TyreController::class, 'updateStintKm']);
    Route::post('tyres/{tyre}/rotate', [TyreController::class, 'rotate']);
    Route::post('tyres/{tyre}/damage', [TyreController::class, 'damage']);
    Route::post('tyres/{tyre}/inspections', [TyreController::class, 'inspection']);
    Route::post('tyres/{tyre}/expenses', [TyreController::class, 'expense']);
    Route::get('tyre-installations/{tyre_installation}/removal-preview', [TyreController::class, 'removalPreview']);
    Route::post('tyres/{tyre}/scrap', [TyreController::class, 'scrap']);
    Route::get('vehicles/{vehicle}/tyre-positions', [TyreController::class, 'vehiclePositions']);
    Route::get('vehicles/{vehicle}/tyre-history', [TyreController::class, 'vehicleTyreHistory']);
    Route::get('/reports/tyres/life', [TyreReportController::class, 'tyreLife']);
    Route::get('/reports/tyres/cost-per-km', [TyreReportController::class, 'costPerKm']);
    Route::get('/reports/tyres/replacements', [TyreReportController::class, 'replacements']);
    Route::get('/reports/tyres/vehicle-wise', [TyreReportController::class, 'vehicleWiseUsage']);
    Route::get('/reports/tyres/expense-summary', [TyreReportController::class, 'expenseSummary']);
    Route::get('/reports/tyres/export', [TyreReportController::class, 'export']);
    Route::get('document-types', [\App\Http\Controllers\Api\DocumentTypeController::class, 'index']);
    Route::post('document-types', [\App\Http\Controllers\Api\DocumentTypeController::class, 'store']);
    Route::get('document-types/{document_type}', [\App\Http\Controllers\Api\DocumentTypeController::class, 'show']);
    Route::put('document-types/{document_type}', [\App\Http\Controllers\Api\DocumentTypeController::class, 'update']);
    Route::delete('document-types/{document_type}', [\App\Http\Controllers\Api\DocumentTypeController::class, 'destroy']);

    Route::get('vehicle-documents/{vehicle_document}/preview', [VehicleDocumentController::class, 'preview']);
    Route::get('vehicle-documents/{vehicle_document}/download', [VehicleDocumentController::class, 'download']);
    Route::apiResource('vehicle-documents', VehicleDocumentController::class);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/reports/attendance', [ReportController::class, 'attendance']);
    Route::get('/reports/trips', [ReportController::class, 'trips']);
    Route::get('/reports/trips/profitability', [TripReportController::class, 'profitability']);
    Route::get('/reports/trips/vehicle-wise', [TripReportController::class, 'vehicleWise']);
    Route::get('/reports/trips/driver-wise', [TripReportController::class, 'driverWise']);
    Route::get('/reports/trips/expense-summary', [TripReportController::class, 'expenseSummary']);
    Route::get('/reports/trips/route-profitability', [TripReportController::class, 'routeProfitability']);
    Route::get('/reports/trips/export', [TripReportController::class, 'export']);
    Route::get('/reports/fleet-monthly', [FleetMonthlyReportController::class, 'show']);
    Route::get('/reports/fleet-monthly/export', [FleetMonthlyReportController::class, 'export']);
    Route::get('/reports/maintenance/cost-summary', [VehicleMaintenanceReportController::class, 'costSummary']);
    Route::get('/reports/maintenance/truck-wise', [VehicleMaintenanceReportController::class, 'truckWise']);
    Route::get('/reports/maintenance/due-services', [VehicleMaintenanceReportController::class, 'dueServices']);
    Route::get('/reports/maintenance/export', [VehicleMaintenanceReportController::class, 'export']);
    Route::get('maintenance-service-types', [MaintenanceServiceTypeController::class, 'index']);
    Route::get('vehicle-maintenances/due', [VehicleMaintenanceController::class, 'due']);
    Route::get('vehicles/{vehicle}/maintenances', [VehicleMaintenanceController::class, 'forVehicle']);
    Route::apiResource('vehicle-maintenances', VehicleMaintenanceController::class);

    Route::get('/compliance', [ComplianceController::class, 'index']);
    Route::apiResource('challans', ChallanController::class);
    Route::post('/compliance/challans', [ComplianceController::class, 'storeChallan']);
    Route::post('/compliance/emis', [ComplianceController::class, 'storeEmi']);
});
