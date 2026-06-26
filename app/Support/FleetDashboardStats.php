<?php

namespace App\Support;

use App\Support\ApiDateTime;
use App\Driver;
use App\Enums\AttendanceStatus;
use App\Enums\TripStatus;
use App\Enums\TyreExpenseType;
use App\Enums\TyreStatus;
use App\Modules\DriverManagement\Services\AttendanceCalendarService;
use App\Trip;
use App\Tyre;
use App\TyreExpense;
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
            'generated_at' => ApiDateTime::toIso(now()),
        ];

        if ($user->hasPermission('trips.view')) {
            $payload['trips'] = self::buildTripStats($fromDate, $toDate);
        }

        if ($user->hasPermission('tyres.view')) {
            $payload['tyres'] = array_merge(
                self::buildTyreInventoryStats(),
                self::buildTyrePeriodStats($fromDate, $toDate),
            );
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
     * @return array<string, float|int>
     */
    private static function buildTripStats(string $fromDate, string $toDate): array
    {
        $baseQuery = Trip::query()
            ->whereDate('start_at', '>=', $fromDate)
            ->whereDate('start_at', '<=', $toDate);

        $financialQuery = (clone $baseQuery)
            ->where('status', TripStatus::Completed->value);

        $totalFreight = (float) (clone $financialQuery)->sum('total_freight_amount');
        $totalExpenses = (float) (clone $financialQuery)->sum('total_expense_amount');

        return [
            'total_count' => (clone $baseQuery)->count(),
            'created_count' => (clone $baseQuery)->where('status', TripStatus::Created->value)->count(),
            'in_progress_count' => (clone $baseQuery)->where('status', TripStatus::InProgress->value)->count(),
            'completed_count' => (clone $baseQuery)->where('status', TripStatus::Completed->value)->count(),
            'cancelled_count' => (clone $baseQuery)->where('status', TripStatus::Cancelled->value)->count(),
            'total_freight_amount' => round($totalFreight, 2),
            'total_expense_amount' => round($totalExpenses, 2),
            'profit_amount' => round($totalFreight - $totalExpenses, 2),
            'running_trips' => self::buildRunningTripsList(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildRunningTripsList(): array
    {
        return Trip::query()
            ->with(['vehicle:id,registration_number', 'driver:id,name'])
            ->where('status', TripStatus::InProgress->value)
            ->latest('start_at')
            ->limit(5)
            ->get()
            ->map(fn (Trip $trip): array => [
                'id' => $trip->id,
                'trip_number' => $trip->trip_number,
                'vehicle_registration' => $trip->vehicle?->registration_number,
                'driver_name' => $trip->driver?->name,
                'start_at' => ApiDateTime::toIso($trip->start_at),
            ])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private static function buildTyreInventoryStats(): array
    {
        return [
            'total_count' => Tyre::query()->count(),
            'in_stock_count' => Tyre::query()->where('status', TyreStatus::InStock->value)->count(),
            'installed_count' => Tyre::query()->where('status', TyreStatus::Installed->value)->count(),
            'scrapped_count' => Tyre::query()->where('status', TyreStatus::Scrapped->value)->count(),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private static function buildTyrePeriodStats(string $fromDate, string $toDate): array
    {
        $purchaseQuery = Tyre::query()
            ->whereNotNull('purchase_date')
            ->whereDate('purchase_date', '>=', $fromDate)
            ->whereDate('purchase_date', '<=', $toDate);

        $scrappedQuery = Tyre::query()
            ->where('status', TyreStatus::Scrapped->value)
            ->whereNotNull('scrapped_at')
            ->whereDate('scrapped_at', '>=', $fromDate)
            ->whereDate('scrapped_at', '<=', $toDate);

        return [
            'purchased_count' => (clone $purchaseQuery)->count(),
            'purchased_total_cost' => round((float) (clone $purchaseQuery)->sum('purchase_cost'), 2),
            'expenses_total' => round((float) TyreExpense::query()
                ->where('expense_type', '!=', TyreExpenseType::Purchase->value)
                ->whereDate('expense_date', '>=', $fromDate)
                ->whereDate('expense_date', '<=', $toDate)
                ->sum('amount'), 2),
            'period_scrapped_count' => (clone $scrappedQuery)->count(),
            'period_scrapped_recovery_amount' => round((float) (clone $scrappedQuery)->sum('scrap_sale_amount'), 2),
        ];
    }
}
