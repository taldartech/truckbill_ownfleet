<?php

namespace App\Support;

use App\Attendance;
use App\Driver;
use App\Enums\AttendanceStatus;
use App\User;
use App\Vehicle;
use App\VehicleDocument;
use Illuminate\Support\Carbon;

class FleetDashboardStats
{
    private const DOCUMENT_EXPIRY_WINDOW_DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public static function build(User $user): array
    {
        $today = Carbon::today()->toDateString();

        $fleet = [
            'vehicles_count' => Vehicle::query()->count(),
            'drivers_count' => Driver::query()->where('is_active', true)->count(),
            'present_today_count' => Attendance::query()
                ->whereDate('attendance_date', $today)
                ->where('status', AttendanceStatus::Present->value)
                ->count(),
            'absent_today_count' => Attendance::query()
                ->whereDate('attendance_date', $today)
                ->where('status', AttendanceStatus::Absent->value)
                ->count(),
            'attendance_marked_today_count' => Attendance::query()
                ->whereDate('attendance_date', $today)
                ->count(),
        ];

        if ($user->hasPermission('vehicle_documents.view')) {
            $fleet['expiring_documents_count'] = VehicleDocument::query()
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', $today)
                ->whereDate('expiry_date', '<=', Carbon::today()->addDays(self::DOCUMENT_EXPIRY_WINDOW_DAYS)->toDateString())
                ->count();
        }

        if ($user->hasPermission('drivers.view')) {
            $fleet['license_expiring_count'] = Driver::query()
                ->where('is_active', true)
                ->whereNotNull('license_expiry_date')
                ->whereDate('license_expiry_date', '>=', $today)
                ->whereDate('license_expiry_date', '<=', Carbon::today()->addDays(self::DOCUMENT_EXPIRY_WINDOW_DAYS)->toDateString())
                ->count();
        }

        return [
            'fleet' => $fleet,
            'generated_at' => now()->toISOString(),
        ];
    }
}
