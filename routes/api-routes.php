<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ComplianceController;
use App\Http\Controllers\Api\DashboardController;
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
    Route::apiResource('drivers', DriverController::class);
    Route::apiResource('driver-assignments', DriverAssignmentController::class);
    Route::apiResource('attendances', AttendanceController::class);
    Route::apiResource('trips', TripController::class);
    Route::apiResource('vehicle-documents', VehicleDocumentController::class);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/reports/attendance', [ReportController::class, 'attendance']);
    Route::get('/reports/trips', [ReportController::class, 'trips']);

    Route::get('/compliance', [ComplianceController::class, 'index']);
    Route::post('/compliance/challans', [ComplianceController::class, 'storeChallan']);
    Route::post('/compliance/emis', [ComplianceController::class, 'storeEmi']);
});
