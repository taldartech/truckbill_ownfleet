<?php

namespace App\Support;

use App\Driver;
use App\Enums\TripExpenseType;
use App\Enums\TripStatus;
use App\Modules\VehicleMaintenanceManagement\Services\VehicleMaintenanceService;
use App\Trip;
use App\TripExpense;
use App\TripRouteLeg;
use App\User;
use App\VehicleDocument;
use App\VehicleMaintenance;
use Illuminate\Support\Carbon;

class FleetComplianceAlerts
{
    private const DOCUMENT_EXPIRY_WINDOW_DAYS = 30;

    /**
     * @return array{
     *     documents: list<array<string, mixed>>,
     *     licenses: list<array<string, mixed>>,
     *     due_services: list<array<string, mixed>>,
     *     eway_bills: list<array<string, mixed>>,
     *     fuel_alerts: list<array<string, mixed>>,
     *     alerts: list<array<string, mixed>>
     * }
     */
    public static function build(User $user): array
    {
        $today = Carbon::today();
        $documents = [];
        $licenses = [];
        $dueServices = [];
        $ewayBills = [];
        $fuelAlerts = [];

        $windowEnd = $today->copy()->addDays(self::DOCUMENT_EXPIRY_WINDOW_DAYS)->toDateString();

        if ($user->hasPermission('vehicle_documents.view')) {
            $documentQuery = VehicleDocument::query()
                ->with('vehicle:id,registration_number')
                ->whereNotNull('expiry_date')
                ->where('expiry_date', '<=', $windowEnd)
                ->orderBy('expiry_date')
                ->limit(200);

            $documentRows = $documentQuery->get();
            $tenantIds = $documentRows->pluck('tenant_id')->unique()->filter()->values();

            foreach ($tenantIds as $tenantId) {
                DocumentTypeDefaults::labelsForTenant((string) $tenantId);
            }

            foreach ($documentRows as $document) {
                $documents[] = self::formatDocumentAlert($document, $today);
            }

            $documents = VehicleDocumentStatus::sortByUrgency($documents);
        }

        if ($user->hasPermission('drivers.view')) {
            $driverQuery = Driver::query()
                ->where('is_active', true)
                ->whereNotNull('license_expiry_date')
                ->where('license_expiry_date', '<=', $windowEnd)
                ->orderBy('license_expiry_date')
                ->limit(200);

            foreach ($driverQuery->get(['id', 'name', 'license_number', 'license_expiry_date']) as $driver) {
                $expiryDate = Carbon::parse($driver->license_expiry_date)->startOfDay();
                $daysRemaining = (int) $today->diffInDays($expiryDate, false);
                $isExpired = $expiryDate->lt($today);
                $status = VehicleDocumentStatus::compute($expiryDate, self::DOCUMENT_EXPIRY_WINDOW_DAYS, $today);

                $licenses[] = [
                    'driver_id' => $driver->id,
                    'driver_name' => $driver->name,
                    'license_number' => $driver->license_number,
                    'license_expiry_date' => $expiryDate->toDateString(),
                    'status' => $isExpired ? 'expired' : 'expiring',
                    'days_remaining' => $daysRemaining,
                    ...$status,
                ];
            }

            $licenses = VehicleDocumentStatus::sortByUrgency($licenses);
        }

        if ($user->hasPermission('maintenance.view')) {
            $dueServices = app(VehicleMaintenanceService::class)
                ->dueServicesForAlerts()
                ->map(fn (VehicleMaintenance $maintenance): array => self::formatDueServiceAlert($maintenance, $today))
                ->sortBy([
                    ['sort_priority', 'asc'],
                    ['days_overdue', 'desc'],
                    ['vehicle_registration', 'asc'],
                ])
                ->values()
                ->map(function (array $alert): array {
                    unset($alert['sort_priority'], $alert['days_overdue']);

                    return $alert;
                })
                ->all();
        }

        if ($user->hasPermission('trips.view')) {

            $ewayBillEnd = $today->copy()->addDay()->toDateString();

            $ewayBillQuery = TripRouteLeg::query()
                ->with([
                    'trip.vehicle:id,registration_number',
                ])
                ->whereHas('trip', function ($query) {
                    $query->where('status', '!=', TripStatus::Completed->value);
                })
                ->whereNotNull('ewaybill_no')
                ->where('ewaybill_no', '!=', '')
                ->whereNotNull('ewaybill_expiry')
                ->whereDate('ewaybill_expiry', '<=', $ewayBillEnd);

            foreach ($ewayBillQuery->get() as $leg) {
                $ewayBills[] = self::formatEwayBillAlert($leg, $today);
            }

            usort($ewayBills, function (array $a, array $b): int {
                if ($a['sort_priority'] !== $b['sort_priority']) {
                    return $a['sort_priority'] <=> $b['sort_priority'];
                }

                return $a['expiry_date'] <=> $b['expiry_date'];
            });

            $ewayBills = array_map(function (array $alert): array {
                unset($alert['sort_priority']);

                return $alert;
            }, $ewayBills);

            $fuelWindowStart = $today->copy()->subDays(30)->startOfDay();

            $tripsQuery = Trip::query()
                ->with([
                    'vehicle:id,registration_number,fuel_tank_capacity_ltr,default_mileage_km_per_liter',
                    'driver:id,name',
                    'expenses' => function ($query) {
                        $query->where('expense_type', TripExpenseType::Diesel->value);
                    },
                ])
                ->where('status', '!=', TripStatus::Cancelled->value)
                ->where(function ($query) use ($fuelWindowStart) {
                    $query->where('start_at', '>=', $fuelWindowStart)
                        ->orWhere('created_at', '>=', $fuelWindowStart)
                        ->orWhere('status', TripStatus::InProgress->value);
                })
                ->latest('start_at')
                ->limit(100);

            foreach ($tripsQuery->get() as $trip) {
                self::detectFuelAlertsForTrip($trip, $fuelAlerts, $today);
            }

            usort($fuelAlerts, function (array $a, array $b): int {
                if ($a['sort_priority'] !== $b['sort_priority']) {
                    return $a['sort_priority'] <=> $b['sort_priority'];
                }

                return ($b['date'] ?? '') <=> ($a['date'] ?? '');
            });

            $fuelAlerts = array_map(function (array $alert): array {
                unset($alert['sort_priority']);

                return $alert;
            }, $fuelAlerts);
        }

        $alerts = collect([
            ...array_map(
                fn (array $alert) => self::normalizeDocumentAlert($alert),
                $documents
            ),

            ...array_map(
                fn (array $alert) => self::normalizeDriverAlert($alert),
                $licenses
            ),

            ...array_map(
                fn (array $alert) => self::normalizeMaintenanceAlert($alert),
                $dueServices
            ),

            ...array_map(
                fn (array $alert) => self::normalizeEwayBillAlert($alert),
                $ewayBills
            ),

            ...array_map(
                fn (array $alert) => self::normalizeFuelAlert($alert),
                $fuelAlerts
            ),
        ])
            ->sortBy([
                ['severity_priority', 'asc'],
                ['due_date', 'asc'],
            ])
            ->values()
            ->map(function (array $alert) {
                unset($alert['severity_priority']);

                return $alert;
            })
            ->all();

        return [
            'documents' => $documents,
            'licenses' => $licenses,
            'due_services' => $dueServices,
            'eway_bills' => $ewayBills,
            'fuel_alerts' => $fuelAlerts,

            'alerts' => $alerts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function formatDueServiceAlert(VehicleMaintenance $maintenance, Carbon $today): array
    {
        $vehicleOdometer = $maintenance->vehicle?->odometer_km !== null
            ? (float) $maintenance->vehicle->odometer_km
            : null;
        $dueKm = $maintenance->next_service_due_km !== null
            ? (float) $maintenance->next_service_due_km
            : null;
        $dueDate = $maintenance->next_service_due_date?->toDateString();
        $overdueReason = MaintenanceDueStatus::overdueReason($vehicleOdometer, $dueKm, $dueDate, $today->toDateString());
        $daysOverdue = $dueDate !== null && $dueDate < $today->toDateString()
            ? (int) Carbon::parse($dueDate)->startOfDay()->diffInDays($today)
            : 0;

        return [
            'id' => $maintenance->id,
            'vehicle_id' => $maintenance->vehicle_id,
            'vehicle_registration' => $maintenance->vehicle?->registration_number,
            'maintenance_service_type_id' => $maintenance->maintenance_service_type_id,
            'service_type' => $maintenance->maintenanceServiceType?->code,
            'service_type_label' => $maintenance->maintenanceServiceType?->name,
            'service_date' => $maintenance->service_date?->toDateString(),
            'odometer_km' => $vehicleOdometer,
            'next_service_due_km' => $dueKm,
            'next_service_due_date' => $dueDate,
            'overdue_reason' => $overdueReason,
            'overdue_reason_label' => MaintenanceDueStatus::overdueReasonLabel($overdueReason),
            'status' => 'due',
            'sort_priority' => match ($overdueReason) {
                'both' => 0,
                'date' => 1,
                'km' => 2,
                default => 3,
            },
            'days_overdue' => $daysOverdue,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function formatDocumentAlert(VehicleDocument $document, Carbon $today): array
    {
        $expiryDate = $document->expiry_date?->copy()->startOfDay();
        $isExpired = $expiryDate !== null && $expiryDate->lt($today);
        $code = (string) $document->document_type;
        $enumType = \App\Enums\VehicleDocumentType::tryFrom($code);
        $label = \App\Support\DocumentTypeDefaults::labelFor((string) $document->tenant_id, $code)
            ?? $enumType?->label()
            ?? $code;

        $status = VehicleDocumentStatus::compute(
            $expiryDate,
            $document->reminder_days_before,
            $today,
        );

        return [
            'id' => $document->id,
            'vehicle_id' => $document->vehicle_id,
            'vehicle_registration' => $document->vehicle?->registration_number,
            'document_type' => $code,
            'document_type_label' => $label,
            'expiry_date' => $expiryDate?->toDateString(),
            'reminder_days_before' => $document->reminder_days_before,
            'status' => $isExpired ? 'expired' : 'expiring',
            ...$status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function formatEwayBillAlert(
        TripRouteLeg $leg,
        Carbon $today
    ): array {
        $expiryDate = $leg->ewaybill_expiry?->copy()->startOfDay();

        $daysRemaining = $expiryDate !== null
            ? (int) $today->diffInDays($expiryDate, false)
            : null;

        $isExpired = $expiryDate !== null && $expiryDate->lt($today);

        return [
            'id' => $leg->id,
            'trip_id' => $leg->trip_id,
            'vehicle_id' => $leg->trip?->vehicle_id,
            'vehicle_registration' => $leg->trip?->vehicle?->registration_number,
            'ewaybill_no' => $leg->ewaybill_no,
            'expiry_date' => $expiryDate?->toDateString(),
            'days_remaining' => $daysRemaining,
            'status' => $isExpired ? 'expired' : 'expiring',
            'sort_priority' => $isExpired ? 0 : 1,
        ];
    }

    private static function normalizeDocumentAlert(array $alert): array
    {
        $isExpired =
            ($alert['status'] ?? null) === 'expired'
            || ($alert['status_tier'] ?? null) === 'expired';

        return [
            'id' => 'document-'.$alert['id'],

            'category' => 'vehicle_documents',
            'category_label' => 'Vehicle Documents',

            'type' => 'document_expiry',

            'title' => $alert['document_type_label'] ?? 'Vehicle Document',

            'reference' => $alert['vehicle_registration'] ?? '—',

            'reference_type' => 'vehicle',

            'vehicle_id' => $alert['vehicle_id'] ?? null,

            'due_date' => $alert['expiry_date'] ?? null,

            'severity' => $isExpired ? 'critical' : 'warning',

            'severity_priority' => $isExpired ? 0 : 1,

            'status' => $alert['status'] ?? null,

            'days_remaining' => $alert['days_remaining'] ?? null,

            'source' => $alert,
        ];
    }

    private static function normalizeDriverAlert(array $alert): array
    {
        $isExpired =
            ($alert['status'] ?? null) === 'expired'
            || ($alert['status_tier'] ?? null) === 'expired';

        return [
            'id' => 'driver-license-'.$alert['driver_id'],

            'category' => 'drivers',
            'category_label' => 'Drivers',

            'type' => 'license_expiry',

            'title' => 'Driver License Expiry',

            'reference' => $alert['driver_name'] ?? '—',

            'reference_type' => 'driver',

            'driver_id' => $alert['driver_id'] ?? null,

            'license_number' => $alert['license_number'] ?? null,

            'due_date' => $alert['license_expiry_date'] ?? null,

            'severity' => $isExpired ? 'critical' : 'warning',

            'severity_priority' => $isExpired ? 0 : 1,

            'status' => $alert['status'] ?? null,

            'days_remaining' => $alert['days_remaining'] ?? null,

            'source' => $alert,
        ];
    }

    private static function normalizeMaintenanceAlert(array $alert): array
    {
        $isOverdue =
            ($alert['next_service_due_date'] ?? null) !== null
            && Carbon::parse($alert['next_service_due_date'])->lt(Carbon::today());

        return [
            'id' => 'maintenance-'.$alert['id'],

            'category' => 'maintenance',
            'category_label' => 'Maintenance',

            'type' => 'service_due',

            'title' => $alert['service_type_label']
                ?? $alert['service_type']
                ?? 'Vehicle Service',

            'reference' => $alert['vehicle_registration'] ?? '—',

            'reference_type' => 'vehicle',

            'vehicle_id' => $alert['vehicle_id'] ?? null,

            'due_date' => $alert['next_service_due_date'] ?? null,

            'severity' => $isOverdue ? 'critical' : 'warning',

            'severity_priority' => $isOverdue ? 0 : 1,

            'status' => $alert['status'] ?? 'due',

            'overdue_reason' => $alert['overdue_reason'] ?? null,

            'overdue_reason_label' => $alert['overdue_reason_label'] ?? null,

            'source' => $alert,
        ];
    }

    private static function normalizeEwayBillAlert(array $alert): array
    {
        $isExpired = ($alert['status'] ?? null) === 'expired';

        return [
            'id' => 'ewaybill-'.$alert['id'],

            'category' => 'ewaybill',
            'category_label' => 'E-Way Bills',

            'type' => 'ewaybill_expiry',

            'title' => 'E-Way Bill Expiry',

            'reference' => $alert['vehicle_registration']
                ?? ('Trip #'.($alert['trip_id'] ?? '—')),

            'reference_type' => 'trip',

            'trip_id' => $alert['trip_id'] ?? null,

            'vehicle_id' => $alert['vehicle_id'] ?? null,

            'ewaybill_no' => $alert['ewaybill_no'] ?? null,

            'due_date' => $alert['expiry_date'] ?? null,

            'severity' => $isExpired ? 'critical' : 'warning',

            'severity_priority' => $isExpired ? 0 : 1,

            'status' => $alert['status'] ?? null,

            'days_remaining' => $alert['days_remaining'] ?? null,

            'source' => $alert,
        ];
    }

    /**
     * @param list<array<string, mixed>> $alerts
     */
    private static function detectFuelAlertsForTrip(Trip $trip, array &$alerts, Carbon $today): void
    {
        $dieselExpenses = $trip->expenses;
        $totalLiters = (float) $dieselExpenses->sum('fuel_liters');
        $distance = (float) ($trip->total_distance_km ?? 0);

        if ($distance <= 0 && $trip->start_meter_reading_km !== null && $trip->end_meter_reading_km !== null) {
            $distance = max(0, (float) $trip->end_meter_reading_km - (float) $trip->start_meter_reading_km);
        }

        $expectedMileage = (float) ($trip->planned_mileage_km_per_liter
            ?? $trip->vehicle?->default_mileage_km_per_liter
            ?? 0);

        $expectedLiters = (float) ($trip->planned_diesel_liters ?? 0);
        if ($expectedLiters <= 0 && $expectedMileage > 0 && $distance > 0) {
            $expectedLiters = round($distance / $expectedMileage, 2);
        }

        $tripDate = $trip->start_at?->toDateString() ?? $trip->created_at?->toDateString() ?? $today->toDateString();
        $vehicleReg = $trip->vehicle?->registration_number;
        $tripNum = $trip->trip_number ?? (string) $trip->id;

        // 1. Missing Odometer Checks
        // 1a. Diesel refill without odometer
        foreach ($dieselExpenses as $expense) {
            if ((float) $expense->fuel_liters > 0 && ($expense->fuel_odometer_km === null || (float) $expense->fuel_odometer_km <= 0)) {
                $alerts[] = [
                    'id' => 'fuel-refill-no-odo-'.$expense->id,
                    'trip_id' => $trip->id,
                    'trip_number' => $tripNum,
                    'vehicle_id' => $trip->vehicle_id,
                    'vehicle_registration' => $vehicleReg,
                    'driver_id' => $trip->driver_id,
                    'driver_name' => $trip->driver?->name,
                    'alert_type' => 'missing_odometer',
                    'title' => 'Missing Odometer on Fuel Refill',
                    'description' => "Refill of {$expense->fuel_liters} L on ".($expense->expense_date?->toDateString() ?? $tripDate).' has no odometer reading.',
                    'severity' => 'warning',
                    'sort_priority' => 1,
                    'date' => $expense->expense_date?->toDateString() ?? $tripDate,
                    'status' => 'warning',
                ];
            }

            // 2a. Refill exceeds fuel tank capacity
            $tankCapacity = (float) ($trip->vehicle?->fuel_tank_capacity_ltr ?? 0);
            if ($tankCapacity > 0 && (float) $expense->fuel_liters > $tankCapacity) {
                $alerts[] = [
                    'id' => 'fuel-exceeds-tank-'.$expense->id,
                    'trip_id' => $trip->id,
                    'trip_number' => $tripNum,
                    'vehicle_id' => $trip->vehicle_id,
                    'vehicle_registration' => $vehicleReg,
                    'driver_id' => $trip->driver_id,
                    'driver_name' => $trip->driver?->name,
                    'alert_type' => 'fuel_consumption_threshold',
                    'title' => 'Refill Exceeds Tank Capacity',
                    'description' => "Refill of {$expense->fuel_liters} L exceeds vehicle tank capacity of {$tankCapacity} L.",
                    'severity' => 'critical',
                    'sort_priority' => 0,
                    'date' => $expense->expense_date?->toDateString() ?? $tripDate,
                    'status' => 'critical',
                ];
            }
        }

        // 1b. Completed trip missing meter reading
        $tripStatus = $trip->status instanceof TripStatus ? $trip->status : TripStatus::tryFrom((string) $trip->status);
        if ($tripStatus === TripStatus::Completed && ($trip->start_meter_reading_km === null || $trip->end_meter_reading_km === null)) {
            $alerts[] = [
                'id' => 'fuel-trip-no-odo-'.$trip->id,
                'trip_id' => $trip->id,
                'trip_number' => $tripNum,
                'vehicle_id' => $trip->vehicle_id,
                'vehicle_registration' => $vehicleReg,
                'driver_id' => $trip->driver_id,
                'driver_name' => $trip->driver?->name,
                'alert_type' => 'missing_odometer',
                'title' => 'Missing Trip Odometer Readings',
                'description' => "Completed trip #{$tripNum} is missing start or end odometer readings.",
                'severity' => 'warning',
                'sort_priority' => 1,
                'date' => $tripDate,
                'status' => 'warning',
            ];
        }

        // 2b. Fuel Consumption Above Threshold (trip consumption significantly exceeding planned)
        if ($expectedLiters > 0 && $totalLiters > ($expectedLiters * 1.30) && ($totalLiters - $expectedLiters) >= 30) {
            $excessLiters = round($totalLiters - $expectedLiters, 1);
            $excessPct = round((($totalLiters - $expectedLiters) / $expectedLiters) * 100, 1);
            $isCritical = $excessPct >= 50;

            $alerts[] = [
                'id' => 'fuel-high-consumption-'.$trip->id,
                'trip_id' => $trip->id,
                'trip_number' => $tripNum,
                'vehicle_id' => $trip->vehicle_id,
                'vehicle_registration' => $vehicleReg,
                'driver_id' => $trip->driver_id,
                'driver_name' => $trip->driver?->name,
                'alert_type' => 'fuel_consumption_threshold',
                'title' => 'Fuel Consumption Above Threshold',
                'description' => "Trip #{$tripNum} consumed {$totalLiters} L vs {$expectedLiters} L planned (+{$excessLiters} L / +{$excessPct}%).",
                'severity' => $isCritical ? 'critical' : 'warning',
                'sort_priority' => $isCritical ? 0 : 1,
                'date' => $tripDate,
                'status' => $isCritical ? 'critical' : 'warning',
            ];
        }

        // 3. Mileage Below Expected
        if ($distance > 10 && $totalLiters > 0 && $expectedMileage > 0) {
            $actualMileage = round($distance / $totalLiters, 2);

            if ($actualMileage < ($expectedMileage * 0.80)) {
                $dropPct = round((($expectedMileage - $actualMileage) / $expectedMileage) * 100, 1);
                $isCritical = $actualMileage < ($expectedMileage * 0.65);

                $alerts[] = [
                    'id' => 'fuel-low-mileage-'.$trip->id,
                    'trip_id' => $trip->id,
                    'trip_number' => $tripNum,
                    'vehicle_id' => $trip->vehicle_id,
                    'vehicle_registration' => $vehicleReg,
                    'driver_id' => $trip->driver_id,
                    'driver_name' => $trip->driver?->name,
                    'alert_type' => 'mileage_below_expected',
                    'title' => 'Mileage Below Expected',
                    'description' => "Recorded {$actualMileage} KM/L vs expected {$expectedMileage} KM/L (-{$dropPct}%).",
                    'severity' => $isCritical ? 'critical' : 'warning',
                    'sort_priority' => $isCritical ? 0 : 1,
                    'date' => $tripDate,
                    'status' => $isCritical ? 'critical' : 'warning',
                ];
            }
        }

        // 4. Abnormal Fuel Variance
        if ($expectedLiters > 0 && $totalLiters > 0) {
            $variance = round($totalLiters - $expectedLiters, 2);
            $variancePct = round(($variance / $expectedLiters) * 100, 1);

            if ($variance >= 50 && $variancePct >= 25) {
                $alerts[] = [
                    'id' => 'fuel-abnormal-variance-'.$trip->id,
                    'trip_id' => $trip->id,
                    'trip_number' => $tripNum,
                    'vehicle_id' => $trip->vehicle_id,
                    'vehicle_registration' => $vehicleReg,
                    'driver_id' => $trip->driver_id,
                    'driver_name' => $trip->driver?->name,
                    'alert_type' => 'abnormal_fuel_variance',
                    'title' => 'Abnormal Fuel Variance',
                    'description' => "Fuel variance of +{$variance} L (+{$variancePct}%) over planned allocation on Trip #{$tripNum}.",
                    'severity' => 'critical',
                    'sort_priority' => 0,
                    'date' => $tripDate,
                    'status' => 'critical',
                ];
            }
        }
    }

    /**
     * @param array<string, mixed> $alert
     * @return array<string, mixed>
     */
    private static function normalizeFuelAlert(array $alert): array
    {
        $isCritical = ($alert['severity'] ?? null) === 'critical';

        return [
            'id' => $alert['id'],
            'category' => 'fuel',
            'category_label' => 'Fuel & Mileage',
            'type' => $alert['alert_type'] ?? 'fuel_alert',
            'title' => $alert['title'],
            'reference' => $alert['vehicle_registration'] ?? ('Trip #'.($alert['trip_number'] ?? $alert['trip_id'] ?? '—')),
            'reference_type' => ! empty($alert['vehicle_registration']) ? 'vehicle' : 'trip',
            'trip_id' => $alert['trip_id'] ?? null,
            'trip_number' => $alert['trip_number'] ?? null,
            'vehicle_id' => $alert['vehicle_id'] ?? null,
            'driver_id' => $alert['driver_id'] ?? null,
            'driver_name' => $alert['driver_name'] ?? null,
            'due_date' => $alert['date'] ?? null,
            'severity' => $alert['severity'] ?? 'warning',
            'severity_priority' => $isCritical ? 0 : 1,
            'status' => $alert['status'] ?? 'active',
            'days_remaining' => null,
            'description' => $alert['description'] ?? null,
            'source' => $alert,
        ];
    }
}
