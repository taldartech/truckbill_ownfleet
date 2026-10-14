<?php

namespace App\Support;

use App\Branch;
use App\Enums\InvoicePaymentStatus;
use App\Enums\TripStatus;
use App\Invoice;
use App\InvoicePayment;
use App\Models\TenantSubscription;
use App\Modules\TripManagement\Support\TripReportScope;
use App\Tenant;
use App\Trip;
use App\TripRouteLeg;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Group-level (all companies) dashboard for a Super Admin who has not entered a company.
 */
class SuperAdminDashboardStats
{
    public const DEFAULT_TREND_MONTHS = 12;

    /**
     * @return array<string, mixed>
     */
    public static function build(
        User $user,
        string $fromDate,
        string $toDate,
        ?string $companyId = null,
        int $trendMonthCount = self::DEFAULT_TREND_MONTHS,
    ): array {
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->startOfDay();
        [$previousFrom, $previousTo] = self::previousPeriod($from, $to);
        $trendMonths = self::trendMonths($to, $trendMonthCount);

        $ownedCompanies = Tenant::query()
            ->where('owner_user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'short_name', 'is_blocked']);

        $companies = $companyId !== null
            ? $ownedCompanies->where('id', $companyId)->values()
            : $ownedCompanies;

        $tenantIds = $companies->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        $companyNames = $companies->mapWithKeys(static fn (Tenant $company): array => [(string) $company->id => $company->name]);

        $branches = Branch::query()
            ->whereIn('company_id', $tenantIds)
            ->orderByDesc('head_office')
            ->orderBy('id')
            ->get(['id', 'company_id', 'branch_name', 'head_office']);
        $branchNames = $branches->pluck('branch_name', 'id');
        $defaultBranchIds = $branches
            ->groupBy(static fn (Branch $branch): string => (string) $branch->company_id)
            ->map(static fn (Collection $companyBranches): int => (int) $companyBranches->first()->id);

        $subscriptions = TenantSubscription::query()
            ->whereIn('tenant_id', $tenantIds)
            ->get()
            ->keyBy(static fn (TenantSubscription $subscription): string => (string) $subscription->tenant_id);

        $currentTrips = self::tripTotalsByTenant($tenantIds, $from, $to);
        $previousTrips = self::tripTotalsByTenant($tenantIds, $previousFrom, $previousTo);
        $current = self::sumTripTotals($currentTrips);
        $previous = self::sumTripTotals($previousTrips);

        $fleet = SuperAdminFleetStats::build($tenantIds, $from, $to, $trendMonths, $companyNames, $branchNames);
        $cash = SuperAdminCashPositionStats::build($tenantIds, $companyNames);

        return [
            'filters' => [
                'company_id' => $companyId,
                'companies' => $ownedCompanies
                    ->map(static fn (Tenant $company): array => ['id' => (string) $company->id, 'name' => $company->name])
                    ->values()
                    ->all(),
            ],
            'period' => [
                'from_date' => $from->toDateString(),
                'to_date' => $to->toDateString(),
                'previous_from_date' => $previousFrom->toDateString(),
                'previous_to_date' => $previousTo->toDateString(),
                'trend_months' => count($trendMonths),
            ],
            'executive' => [
                'companies_count' => $companies->count(),
                'revenue' => self::metric($current['revenue'], $previous['revenue']),
                'expenses' => self::metric($current['cost'], $previous['cost']),
                'profit' => self::metric($current['profit'], $previous['profit']),
                'margin' => [
                    'current' => self::percentOf($current['profit'], $current['revenue']),
                    'previous' => self::percentOf($previous['profit'], $previous['revenue']),
                ],
            ],
            'fleet' => [
                'overview' => $fleet['overview'],
                'utilization' => $fleet['utilization'],
                'idle' => $fleet['idle'],
                'on_trip_by_company' => $fleet['on_trip_by_company'],
            ],
            'activity' => self::activity($tenantIds, $from, $to),
            'trend' => self::trend($tenantIds, $trendMonths, $fleet['monthly_utilization']),
            'companies' => self::companyRows(
                $companies,
                $currentTrips,
                $fleet['by_company'],
                $cash['receivables_by_company'],
                $subscriptions,
                $defaultBranchIds,
            ),
            'cash' => [
                'receivables' => $cash['receivables'],
                'top_outstanding_customers' => $cash['top_outstanding_customers'],
            ],
            'expenses' => SuperAdminExpenseStats::build($tenantIds, $from, $to, $companyNames),
            'attention' => SuperAdminAttentionStats::build(
                $tenantIds,
                $fleet['idle_warning_by_company'],
                $fleet['due_services_by_company'],
                $cash['receivables_by_company'],
                $cash['emis_due_soon_by_company'],
                $subscriptions,
                $companyNames,
                $defaultBranchIds,
            ),
            'generated_at' => ApiDateTime::toIso(now()),
        ];
    }

    /**
     * Month-aligned ranges compare against the same span of the preceding month(s)
     * (e.g. month-to-date vs previous month-to-date); other ranges use the equal-length window before.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function previousPeriod(Carbon $from, Carbon $to): array
    {
        if ($from->day === 1) {
            $monthSpan = (($to->year - $from->year) * 12) + ($to->month - $from->month) + 1;
            $previousFrom = $from->copy()->subMonthsNoOverflow($monthSpan);
            $previousTo = $to->isSameDay($to->copy()->endOfMonth())
                ? $to->copy()->subMonthsNoOverflow($monthSpan)->endOfMonth()->startOfDay()
                : $to->copy()->subMonthsNoOverflow($monthSpan);

            return [$previousFrom, $previousTo];
        }

        $days = (int) $from->diffInDays($to) + 1;

        return [$from->copy()->subDays($days), $from->copy()->subDay()];
    }

    /**
     * @return list<string>
     */
    private static function trendMonths(Carbon $to, int $count): array
    {
        $cursor = $to->copy()->startOfMonth()->subMonthsNoOverflow($count - 1);
        $months = [];

        for ($index = 0; $index < $count; $index++) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonthNoOverflow();
        }

        return $months;
    }

    /**
     * @param  list<string>  $tenantIds
     * @return Builder<Trip>
     */
    private static function reportableTripQuery(array $tenantIds, Carbon $from, Carbon $to): Builder
    {
        $query = SuperAdminPortfolioScope::apply(Trip::query(), $tenantIds);
        TripReportScope::whereReportableStatuses($query, 'trips.status');
        TripReportScope::whereReportDateInRange($query, $from, $to);

        return $query;
    }

    /**
     * @param  list<string>  $tenantIds
     * @return Collection<string, object>
     */
    private static function tripTotalsByTenant(array $tenantIds, Carbon $from, Carbon $to): Collection
    {
        return self::reportableTripQuery($tenantIds, $from, $to)
            ->groupBy('trips.tenant_id')
            ->selectRaw(
                'trips.tenant_id,
                COUNT(*) as trips_count,
                COALESCE(SUM(trips.total_freight_amount), 0) + COALESCE(SUM(trips.total_custom_amount), 0) as revenue,
                COALESCE(SUM(trips.total_expense_amount), 0) as cost',
            )
            ->toBase()
            ->get()
            ->keyBy(static fn (object $row): string => (string) $row->tenant_id);
    }

    /**
     * @param  Collection<string, object>  $tripTotals
     * @return array{revenue: float, cost: float, profit: float, trips: int}
     */
    private static function sumTripTotals(Collection $tripTotals): array
    {
        $revenue = (float) $tripTotals->sum('revenue');
        $cost = (float) $tripTotals->sum('cost');

        return [
            'revenue' => $revenue,
            'cost' => $cost,
            'profit' => $revenue - $cost,
            'trips' => (int) $tripTotals->sum('trips_count'),
        ];
    }

    /**
     * @param  list<string>  $tenantIds
     * @return array<string, mixed>
     */
    private static function activity(array $tenantIds, Carbon $from, Carbon $to): array
    {
        $tripQuery = SuperAdminPortfolioScope::apply(Trip::query(), $tenantIds);
        TripReportScope::whereReportDateInRange($tripQuery, $from, $to);

        $statusCounts = $tripQuery
            ->groupBy('trips.status')
            ->selectRaw('trips.status, COUNT(*) as aggregate')
            ->toBase()
            ->pluck('aggregate', 'status');

        $count = static fn (TripStatus $status): int => (int) ($statusCounts[$status->value] ?? 0);

        $lrQuery = SuperAdminPortfolioScope::apply(TripRouteLeg::query(), $tenantIds)
            ->join('trips', 'trips.id', '=', 'trip_route_legs.trip_id')
            ->whereNull('trips.deleted_at')
            ->whereNotNull('trip_route_legs.customer_id')
            ->where('trip_route_legs.weight_ton', '>', 0);
        TripReportScope::whereReportableStatuses($lrQuery, 'trips.status');
        TripReportScope::whereReportDateInRange($lrQuery, $from, $to);

        $payments = SuperAdminPortfolioScope::apply(InvoicePayment::query(), $tenantIds)
            ->whereDate('invoice_payments.paid_at', '>=', $from->toDateString())
            ->whereDate('invoice_payments.paid_at', '<=', $to->toDateString())
            ->selectRaw('COUNT(*) as payments_count, COALESCE(SUM(invoice_payments.amount), 0) as amount')
            ->toBase()
            ->first();

        return [
            'trips' => [
                'total' => (int) $statusCounts->sum(),
                'completed' => $count(TripStatus::Completed) + $count(TripStatus::Invoiced),
                'invoiced' => $count(TripStatus::Invoiced),
                'in_progress' => $count(TripStatus::InProgress),
                'pending' => $count(TripStatus::Pending),
                'cancelled' => $count(TripStatus::Cancelled),
            ],
            'lrs_count' => $lrQuery->count(),
            'invoices_count' => SuperAdminPortfolioScope::apply(Invoice::query(), $tenantIds)
                ->where('invoices.payment_status', '!=', InvoicePaymentStatus::Cancelled->value)
                ->whereDate('invoices.invoice_date', '>=', $from->toDateString())
                ->whereDate('invoices.invoice_date', '<=', $to->toDateString())
                ->count(),
            'payments_count' => (int) ($payments->payments_count ?? 0),
            'payments_amount' => round((float) ($payments->amount ?? 0), 2),
        ];
    }

    /**
     * @param  list<string>  $tenantIds
     * @param  list<string>  $trendMonths
     * @param  array<string, float>  $monthlyUtilization
     * @return list<array{month: string, revenue: float, cost: float, profit: float, trips: int, utilization: float}>
     */
    private static function trend(array $tenantIds, array $trendMonths, array $monthlyUtilization): array
    {
        $trendStart = Carbon::createFromFormat('Y-m-d', $trendMonths[0].'-01')->startOfDay();
        $trendEnd = Carbon::createFromFormat('Y-m-d', end($trendMonths).'-01')->endOfMonth()->startOfDay();

        $monthlyTotals = self::reportableTripQuery($tenantIds, $trendStart, $trendEnd)
            ->groupByRaw('DATE(trips.start_at)')
            ->selectRaw(
                'DATE(trips.start_at) as trip_date,
                COUNT(*) as trips_count,
                COALESCE(SUM(trips.total_freight_amount), 0) + COALESCE(SUM(trips.total_custom_amount), 0) as revenue,
                COALESCE(SUM(trips.total_expense_amount), 0) as cost',
            )
            ->toBase()
            ->get()
            ->groupBy(static fn (object $row): string => substr((string) $row->trip_date, 0, 7));

        return array_map(static function (string $monthKey) use ($monthlyTotals, $monthlyUtilization): array {
            $rows = $monthlyTotals->get($monthKey, collect());
            $revenue = (float) $rows->sum('revenue');
            $cost = (float) $rows->sum('cost');

            return [
                'month' => $monthKey,
                'revenue' => round($revenue, 2),
                'cost' => round($cost, 2),
                'profit' => round($revenue - $cost, 2),
                'trips' => (int) $rows->sum('trips_count'),
                'utilization' => $monthlyUtilization[$monthKey] ?? 0.0,
            ];
        }, $trendMonths);
    }

    /**
     * @param  Collection<int, Tenant>  $companies
     * @param  Collection<string, object>  $currentTrips
     * @param  array<string, array<string, int|float>>  $fleetByCompany
     * @param  array<string, array{outstanding: float, overdue: float, overdue_count: int}>  $receivablesByCompany
     * @param  Collection<string, TenantSubscription>  $subscriptions
     * @param  Collection<string, int>  $defaultBranchIds
     * @return list<array<string, mixed>>
     */
    private static function companyRows(
        Collection $companies,
        Collection $currentTrips,
        array $fleetByCompany,
        array $receivablesByCompany,
        Collection $subscriptions,
        Collection $defaultBranchIds,
    ): array {
        $today = Carbon::today();

        return $companies
            ->map(static function (Tenant $company) use (
                $currentTrips,
                $fleetByCompany,
                $receivablesByCompany,
                $subscriptions,
                $defaultBranchIds,
                $today,
            ): array {
                $tenantId = (string) $company->id;
                $trips = $currentTrips->get($tenantId);
                $fleet = $fleetByCompany[$tenantId] ?? [];
                $receivables = $receivablesByCompany[$tenantId] ?? [];
                $subscription = $subscriptions->get($tenantId);

                $revenue = (float) ($trips->revenue ?? 0);
                $cost = (float) ($trips->cost ?? 0);

                return [
                    'id' => $tenantId,
                    'name' => $company->name,
                    'is_blocked' => (bool) $company->is_blocked,
                    'default_branch_id' => $defaultBranchIds->get($tenantId),
                    'trips_count' => (int) ($trips->trips_count ?? 0),
                    'revenue' => round($revenue, 2),
                    'cost' => round($cost, 2),
                    'profit' => round($revenue - $cost, 2),
                    'margin' => self::percentOf($revenue - $cost, $revenue),
                    'vehicles_count' => (int) ($fleet['vehicles_count'] ?? 0),
                    'on_trip' => (int) ($fleet['on_trip'] ?? 0),
                    'utilization_rate' => (float) ($fleet['utilization_rate'] ?? 0),
                    'outstanding' => (float) ($receivables['outstanding'] ?? 0),
                    'overdue' => (float) ($receivables['overdue'] ?? 0),
                    'subscription' => $subscription === null ? null : [
                        'status' => $subscription->isActive() ? $subscription->status : 'expired',
                        'ends_on' => $subscription->ends_on?->toDateString(),
                        'days_remaining' => $subscription->ends_on !== null
                            ? (int) $today->diffInDays($subscription->ends_on, false)
                            : null,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{current: float, previous: float, change_percent: float|null}
     */
    private static function metric(float $current, float $previous): array
    {
        return [
            'current' => round($current, 2),
            'previous' => round($previous, 2),
            'change_percent' => $previous != 0.0 ? round((($current - $previous) / abs($previous)) * 100, 1) : null,
        ];
    }

    private static function percentOf(float $part, float $whole): float
    {
        return $whole != 0.0 ? round(($part / $whole) * 100, 1) : 0.0;
    }
}
