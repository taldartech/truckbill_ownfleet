<?php

namespace App\Support;

use App\Attendance;
use App\Challan;
use App\Enums\TripExpenseType;
use App\Modules\TripManagement\Support\TripReportScope;
use App\MonthlyExpense;
use App\TripExpense;
use App\TyreExpense;
use App\VehicleMaintenance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Consolidated operating spend across a Super Admin's companies, grouped into owner-friendly categories.
 *
 * Trip expenses follow the dashboard trip rules (non-cancelled trips, attributed to trip start date),
 * so their total equals the "Direct cost" used for profit.
 */
class SuperAdminExpenseStats
{
    /**
     * @var array<string, string>
     */
    private const CATEGORY_LABELS = [
        'fuel' => 'Fuel',
        'driver' => 'Driver wages & bhatta',
        'maintenance' => 'Maintenance & repairs',
        'tyres' => 'Tyres',
        'toll' => 'Toll',
        'loading' => 'Loading / unloading',
        'office' => 'Office & monthly',
        'other' => 'Other',
    ];

    /**
     * @var array<string, string>
     */
    private const TRIP_EXPENSE_CATEGORY = [
        TripExpenseType::Diesel->value => 'fuel',
        TripExpenseType::Toll->value => 'toll',
        TripExpenseType::DriverExpense->value => 'driver',
        TripExpenseType::FoodExpense->value => 'driver',
        TripExpenseType::Repair->value => 'maintenance',
        TripExpenseType::LoadingCharges->value => 'loading',
        TripExpenseType::UnloadingCharges->value => 'loading',
    ];

    /**
     * @param  list<string>  $tenantIds
     * @param  Collection<string, string>  $companyNames
     * @return array{total: float, direct_cost: float, categories: list<array<string, mixed>>}
     */
    public static function build(array $tenantIds, Carbon $from, Carbon $to, Collection $companyNames): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        /** @var array<string, array<string, float>> $amounts category => [tenant id => amount] */
        $amounts = [];
        $directCost = 0.0;

        $tripExpenses = SuperAdminPortfolioScope::apply(TripExpense::query(), $tenantIds)
            ->join('trips', 'trips.id', '=', 'trip_expenses.trip_id')
            ->whereNull('trips.deleted_at');
        TripReportScope::whereReportableStatuses($tripExpenses, 'trips.status');
        TripReportScope::whereReportDateInRange($tripExpenses, $from, $to);

        foreach ($tripExpenses
            ->groupBy('trip_expenses.tenant_id', 'trip_expenses.expense_type')
            ->selectRaw('trip_expenses.tenant_id, trip_expenses.expense_type, COALESCE(SUM(trip_expenses.amount), 0) as amount')
            ->toBase()
            ->get() as $row) {
            $category = self::TRIP_EXPENSE_CATEGORY[$row->expense_type] ?? 'other';
            self::add($amounts, $category, (string) $row->tenant_id, (float) $row->amount);
            $directCost += (float) $row->amount;
        }

        $sources = [
            'driver' => SuperAdminPortfolioScope::apply(Attendance::query(), $tenantIds)
                ->whereDate('attendances.attendance_date', '>=', $fromDate)
                ->whereDate('attendances.attendance_date', '<=', $toDate)
                ->groupBy('attendances.tenant_id')
                ->selectRaw('attendances.tenant_id, COALESCE(SUM(attendances.wage_amount), 0) + COALESCE(SUM(attendances.substitute_payment_amount), 0) as amount'),
            'maintenance' => SuperAdminPortfolioScope::apply(VehicleMaintenance::query(), $tenantIds)
                ->whereDate('vehicle_maintenances.service_date', '>=', $fromDate)
                ->whereDate('vehicle_maintenances.service_date', '<=', $toDate)
                ->groupBy('vehicle_maintenances.tenant_id')
                ->selectRaw('vehicle_maintenances.tenant_id, COALESCE(SUM(vehicle_maintenances.cost_amount), 0) as amount'),
            'tyres' => SuperAdminPortfolioScope::apply(TyreExpense::query(), $tenantIds)
                ->whereDate('tyre_expenses.expense_date', '>=', $fromDate)
                ->whereDate('tyre_expenses.expense_date', '<=', $toDate)
                ->groupBy('tyre_expenses.tenant_id')
                ->selectRaw('tyre_expenses.tenant_id, COALESCE(SUM(tyre_expenses.amount), 0) as amount'),
            'office' => SuperAdminPortfolioScope::apply(MonthlyExpense::query(), $tenantIds)
                ->whereBetween('monthly_expenses.month', [$from->format('Y-m'), $to->format('Y-m')])
                ->groupBy('monthly_expenses.tenant_id')
                ->selectRaw('monthly_expenses.tenant_id, COALESCE(SUM(monthly_expenses.amount), 0) as amount'),
            'other' => SuperAdminPortfolioScope::apply(Challan::query(), $tenantIds)
                ->whereDate('challans.challan_date', '>=', $fromDate)
                ->whereDate('challans.challan_date', '<=', $toDate)
                ->groupBy('challans.tenant_id')
                ->selectRaw('challans.tenant_id, COALESCE(SUM(challans.amount), 0) as amount'),
        ];

        foreach ($sources as $category => $query) {
            foreach ($query->toBase()->get() as $row) {
                self::add($amounts, $category, (string) $row->tenant_id, (float) $row->amount);
            }
        }

        $total = array_sum(array_map('array_sum', $amounts));

        $categories = collect(self::CATEGORY_LABELS)
            ->map(static function (string $label, string $key) use ($amounts, $total, $companyNames): array {
                $byCompany = $amounts[$key] ?? [];
                $amount = array_sum($byCompany);

                return [
                    'key' => $key,
                    'label' => $label,
                    'amount' => round($amount, 2),
                    'percent' => $total > 0 ? round(($amount / $total) * 100, 1) : 0.0,
                    'companies' => collect($byCompany)
                        ->map(static fn (float $companyAmount, string $tenantId): array => [
                            'company_id' => $tenantId,
                            'company_name' => $companyNames->get($tenantId),
                            'amount' => round($companyAmount, 2),
                        ])
                        ->sortByDesc('amount')
                        ->values()
                        ->all(),
                ];
            })
            ->filter(static fn (array $category): bool => $category['amount'] > 0)
            ->sortByDesc('amount')
            ->values()
            ->all();

        return [
            'total' => round($total, 2),
            'direct_cost' => round($directCost, 2),
            'categories' => $categories,
        ];
    }

    /**
     * @param  array<string, array<string, float>>  $amounts
     */
    private static function add(array &$amounts, string $category, string $tenantId, float $amount): void
    {
        if ($amount == 0.0) {
            return;
        }

        $amounts[$category][$tenantId] = ($amounts[$category][$tenantId] ?? 0) + $amount;
    }
}
