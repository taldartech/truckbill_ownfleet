<?php

namespace App\Support;

use App\Driver;
use App\Enums\TripStatus;
use App\Modules\VehicleMaintenanceManagement\Services\VehicleMaintenanceService;
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
     *     due_services: list<array<string, mixed>>
     *     eway_bills: list<array<string, mixed>>
     * }
     */
    public static function build(User $user): array
    {
        $today = Carbon::today();
        $documents = [];
        $licenses = [];
        $dueServices = [];
        $ewayBills = [];

        $windowEnd = $today->copy()->addDays(self::DOCUMENT_EXPIRY_WINDOW_DAYS)->toDateString();

        if ($user->hasPermission('vehicle_documents.view')) {
            $documentQuery = VehicleDocument::query()
                ->with('vehicle:id,registration_number')
                ->whereNotNull('expiry_date')
                ->where('expiry_date', '<=', $windowEnd);

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
                ->where('license_expiry_date', '<=', $windowEnd);

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
}
