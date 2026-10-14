<?php

namespace App\Support;

use App\Driver;
use App\Enums\TripStatus;
use App\Models\TenantSubscription;
use App\TripRouteLeg;
use App\VehicleDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Action-only alerts for the Super Admin dashboard, each broken down by company.
 */
class SuperAdminAttentionStats
{
    private const EXPIRY_WINDOW_DAYS = 30;

    private const RENEWAL_WINDOW_DAYS = 30;

    /**
     * @param  list<string>  $tenantIds
     * @param  array<string, int>  $idleTrucksByCompany
     * @param  array<string, int>  $dueServicesByCompany
     * @param  array<string, array{outstanding: float, overdue: float, overdue_count: int}>  $receivablesByCompany
     * @param  array<string, int>  $emisDueSoonByCompany
     * @param  Collection<string, TenantSubscription>  $subscriptions
     * @param  Collection<string, string>  $companyNames
     * @param  Collection<string, int>  $defaultBranchIds
     * @return list<array<string, mixed>>
     */
    public static function build(
        array $tenantIds,
        array $idleTrucksByCompany,
        array $dueServicesByCompany,
        array $receivablesByCompany,
        array $emisDueSoonByCompany,
        Collection $subscriptions,
        Collection $companyNames,
        Collection $defaultBranchIds,
    ): array {
        $today = Carbon::today()->toDateString();
        $expiryLimit = Carbon::today()->addDays(self::EXPIRY_WINDOW_DAYS)->toDateString();

        $documentsExpiring = SuperAdminPortfolioScope::apply(VehicleDocument::query(), $tenantIds)
            ->whereNotNull('vehicle_documents.expiry_date')
            ->whereDate('vehicle_documents.expiry_date', '<=', $expiryLimit)
            ->groupBy('vehicle_documents.tenant_id')
            ->selectRaw('vehicle_documents.tenant_id, COUNT(*) as aggregate')
            ->toBase()
            ->pluck('aggregate', 'tenant_id')
            ->all();

        $licensesExpiring = SuperAdminPortfolioScope::apply(Driver::query(), $tenantIds)
            ->where('drivers.is_active', true)
            ->whereNotNull('drivers.license_expiry_date')
            ->whereDate('drivers.license_expiry_date', '<=', $expiryLimit)
            ->groupBy('drivers.tenant_id')
            ->selectRaw('drivers.tenant_id, COUNT(*) as aggregate')
            ->toBase()
            ->pluck('aggregate', 'tenant_id')
            ->all();

        $ewayBillsExpiring = SuperAdminPortfolioScope::apply(TripRouteLeg::query(), $tenantIds)
            ->join('trips', 'trips.id', '=', 'trip_route_legs.trip_id')
            ->whereNull('trips.deleted_at')
            ->whereIn('trips.status', [TripStatus::Pending->value, TripStatus::InProgress->value])
            ->whereNotNull('trip_route_legs.ewaybill_no')
            ->where('trip_route_legs.ewaybill_no', '!=', '')
            ->whereNotNull('trip_route_legs.ewaybill_expiry')
            ->whereDate('trip_route_legs.ewaybill_expiry', '<=', $today)
            ->groupBy('trip_route_legs.tenant_id')
            ->selectRaw('trip_route_legs.tenant_id, COUNT(*) as aggregate')
            ->toBase()
            ->pluck('aggregate', 'tenant_id')
            ->all();

        $expiredSubscriptions = [];
        $renewalsDue = [];

        foreach ($subscriptions as $tenantId => $subscription) {
            if (! $subscription->isActive()) {
                $expiredSubscriptions[$tenantId] = 1;

                continue;
            }

            if ((int) Carbon::today()->diffInDays($subscription->ends_on, false) <= self::RENEWAL_WINDOW_DAYS) {
                $renewalsDue[$tenantId] = 1;
            }
        }

        $definitions = [
            ['key' => 'subscription_expired', 'label' => 'Subscriptions expired', 'severity' => 'danger', 'counts' => $expiredSubscriptions],
            ['key' => 'idle_trucks', 'label' => 'Trucks idle for '.SuperAdminFleetStats::IDLE_WARNING_DAYS.'+ days', 'severity' => 'warning', 'counts' => $idleTrucksByCompany],
            ['key' => 'services_overdue', 'label' => 'Services overdue', 'severity' => 'danger', 'counts' => $dueServicesByCompany],
            ['key' => 'documents_expiring', 'label' => 'Vehicle documents expired or expiring in '.self::EXPIRY_WINDOW_DAYS.' days', 'severity' => 'warning', 'counts' => $documentsExpiring],
            ['key' => 'licenses_expiring', 'label' => 'Driver licenses expired or expiring in '.self::EXPIRY_WINDOW_DAYS.' days', 'severity' => 'warning', 'counts' => $licensesExpiring],
            ['key' => 'eway_bills_expiring', 'label' => 'E-way bills expired or expiring today', 'severity' => 'danger', 'counts' => $ewayBillsExpiring],
            [
                'key' => 'invoices_overdue',
                'label' => 'Outstanding invoices overdue',
                'severity' => 'danger',
                'counts' => array_filter(array_map(
                    static fn (array $row): int => $row['overdue_count'],
                    $receivablesByCompany,
                )),
            ],
            ['key' => 'emis_due', 'label' => 'Vehicle EMIs due within 3 days', 'severity' => 'warning', 'counts' => $emisDueSoonByCompany],
            ['key' => 'renewal_due', 'label' => 'Subscriptions due for renewal in '.self::RENEWAL_WINDOW_DAYS.' days', 'severity' => 'warning', 'counts' => $renewalsDue],
        ];

        $items = [];

        foreach ($definitions as $definition) {
            $companies = collect($definition['counts'])
                ->filter(static fn ($count): bool => (int) $count > 0)
                ->map(static fn ($count, $tenantId): array => [
                    'company_id' => (string) $tenantId,
                    'company_name' => $companyNames->get((string) $tenantId),
                    'default_branch_id' => $defaultBranchIds->get((string) $tenantId),
                    'count' => (int) $count,
                ])
                ->sortByDesc('count')
                ->values();

            if ($companies->isEmpty()) {
                continue;
            }

            $items[] = [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'severity' => $definition['severity'],
                'count' => (int) $companies->sum('count'),
                'companies' => $companies->all(),
            ];
        }

        return $items;
    }
}
