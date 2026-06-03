<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ComplianceController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DriverAdvanceController;
use App\Http\Controllers\Api\DriverAssignmentController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VehicleDocumentController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::apiResource('vehicles', VehicleController::class);
    Route::get('drivers/license-expiring', [DriverController::class, 'licenseExpiring']);
    Route::get('drivers/{driver}/advance-ledger', [DriverAdvanceController::class, 'ledger']);
    Route::post('drivers/{driver}/advance-entries', [DriverAdvanceController::class, 'store']);
    Route::apiResource('drivers', DriverController::class);
    Route::apiResource('driver-assignments', DriverAssignmentController::class);
    Route::get('attendances/month-overview', [AttendanceController::class, 'monthOverview']);
    Route::get('attendances/monthly-wages', [AttendanceController::class, 'monthlyWages']);
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
    Route::apiResource('trips', TripController::class);
    Route::get('vehicle-documents/{vehicle_document}/preview', [VehicleDocumentController::class, 'preview']);
    Route::get('vehicle-documents/{vehicle_document}/download', [VehicleDocumentController::class, 'download']);
    Route::apiResource('vehicle-documents', VehicleDocumentController::class);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/reports/attendance', [ReportController::class, 'attendance']);
    Route::get('/reports/trips', [ReportController::class, 'trips']);

    Route::get('/compliance', [ComplianceController::class, 'index']);
    Route::post('/compliance/challans', [ComplianceController::class, 'storeChallan']);
    Route::post('/compliance/emis', [ComplianceController::class, 'storeEmi']);
});
