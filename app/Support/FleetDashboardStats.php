<?php

namespace App\Support;

use App\Driver;
use App\Enums\AttendanceStatus;
use App\Enums\TripStatus;
use App\Enums\TyreStatus;
use App\Tyre;
use App\Modules\DriverManagement\Services\AttendanceCalendarService;
use App\Trip;
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
    public static function build(User $user, ?string $fromDate = null, ?string $toDate = null): array
    {
        $today = Carbon::today()->toDateString();
        $fromDate = $fromDate ?? $today;
        $toDate = $toDate ?? $fromDate;

        $fleet = [
            'vehicles_count' => Vehicle::query()->count(),
            'active_vehicles_count' => Vehicle::query()->where('is_active', true)->count(),
            'inactive_vehicles_count' => Vehicle::query()->where('is_active', false)->count(),
            'drivers_count' => Driver::query()->where('is_active', true)->count(),
            'inactive_drivers_count' => Driver::query()->where('is_active', false)->count(),
        ];

        if ($user->hasPermission('attendances.view')) {
            $fleet = array_merge($fleet, self::buildAttendanceTodayStats($today));
        }

        if ($user->hasPermission('vehicle_documents.view')) {
            $fleet['expired_documents_count'] = VehicleDocument::query()
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<', $today)
                ->count();

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

        $payload = [
            'fleet' => $fleet,
            'period' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'generated_at' => now()->toISOString(),
        ];

        if ($user->hasPermission('trips.view')) {
            $payload['trips'] = self::buildTripStats($fromDate, $toDate);
        }

        if ($user->hasPermission('tyres.view')) {
            $payload['tyres'] = self::buildTyreStats();
        }

        return $payload;
    }

    /**
     * @return array<string, int>
     */
    private static function buildAttendanceTodayStats(string $today): array
    {
        $overview = app(AttendanceCalendarService::class)->buildOverview($today, $today);

        $present = 0;
        $absent = 0;
        $absentSubstitute = 0;
        $noWorkDay = 0;

        foreach ($overview['rows'] as $row) {
            $cell = $row['days'][$today] ?? null;

            if ($cell === null || empty($cell['status'])) {
                continue;
            }

            $status = strtoupper((string) $cell['status']);

            if ($status === AttendanceStatus::Present->value) {
                $present++;
            } elseif ($status === AttendanceStatus::NoWorkDay->value) {
                $noWorkDay++;
            } elseif ($status === AttendanceStatus::Absent->value) {
                if (! empty($cell['temporary_driver_id'])) {
                    $absentSubstitute++;
                } else {
                    $absent++;
                }
            }
        }

        return [
            'assigned_trucks_today_count' => count($overview['rows']),
            'present_today_count' => $present,
            'absent_today_count' => $absent,
            'absent_substitute_today_count' => $absentSubstitute,
            'no_work_day_today_count' => $noWorkDay,
            'exceptions_today_count' => (int) ($overview['summary']['exceptions'] ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function buildTripStats(string $fromDate, string $toDate): array
    {
        $baseQuery = Trip::query()
            ->whereDate('start_at', '>=', $fromDate)
            ->whereDate('start_at', '<=', $toDate);

        return [
            'total_count' => (clone $baseQuery)->count(),
            'created_count' => (clone $baseQuery)->where('status', TripStatus::Created->value)->count(),
            'in_progress_count' => (clone $baseQuery)->where('status', TripStatus::InProgress->value)->count(),
            'completed_count' => (clone $baseQuery)->where('status', TripStatus::Completed->value)->count(),
            'cancelled_count' => (clone $baseQuery)->where('status', TripStatus::Cancelled->value)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private static function buildTyreStats(): array
    {
        return [
            'total_count' => Tyre::query()->count(),
            'in_stock_count' => Tyre::query()->where('status', TyreStatus::InStock->value)->count(),
            'installed_count' => Tyre::query()->where('status', TyreStatus::Installed->value)->count(),
            'damaged_count' => Tyre::query()->where('status', TyreStatus::Damaged->value)->count(),
            'scrapped_count' => Tyre::query()->where('status', TyreStatus::Scrapped->value)->count(),
        ];
    }
}
