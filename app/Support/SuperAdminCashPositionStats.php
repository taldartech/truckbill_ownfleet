<?php

namespace App\Support;

use App\Customer;
use App\Emi;
use App\Enums\InvoicePaymentStatus;
use App\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cash stuck in the business across a Super Admin's companies: open customer receivables,
 * plus the per-company EMI counts that feed the attention panel.
 */
class SuperAdminCashPositionStats
{
    private const TOP_LIMIT = 5;

    private const EMI_DUE_SOON_DAYS = 3;

    private const EMI_DUE_STATUS = 'DUE';

    /**
     * @param  list<string>  $tenantIds
     * @param  Collection<string, string>  $companyNames
     * @return array<string, mixed>
     */
    public static function build(array $tenantIds, Collection $companyNames): array
    {
        $today = Carbon::today()->toDateString();

        $receivablesByCompany = self::openInvoices($tenantIds)
            ->groupBy('invoices.tenant_id')
            ->selectRaw(
                'invoices.tenant_id,
                COALESCE(SUM(invoices.total_amount - invoices.paid_amount), 0) as outstanding,
                COALESCE(SUM(CASE WHEN DATE(invoices.due_date) = ? THEN invoices.total_amount - invoices.paid_amount ELSE 0 END), 0) as due_today,
                COALESCE(SUM(CASE WHEN DATE(invoices.due_date) < ? THEN invoices.total_amount - invoices.paid_amount ELSE 0 END), 0) as overdue,
                SUM(CASE WHEN DATE(invoices.due_date) < ? THEN 1 ELSE 0 END) as overdue_count',
                [$today, $today, $today],
            )
            ->toBase()
            ->get()
            ->keyBy('tenant_id');

        return [
            'receivables' => [
                'outstanding' => round((float) $receivablesByCompany->sum('outstanding'), 2),
                'due_today' => round((float) $receivablesByCompany->sum('due_today'), 2),
                'overdue' => round((float) $receivablesByCompany->sum('overdue'), 2),
                'overdue_invoices_count' => (int) $receivablesByCompany->sum('overdue_count'),
            ],
            'top_outstanding_customers' => self::topOutstandingCustomers($tenantIds, $companyNames, $today),
            'receivables_by_company' => $receivablesByCompany
                ->map(static fn (object $row): array => [
                    'outstanding' => round((float) $row->outstanding, 2),
                    'overdue' => round((float) $row->overdue, 2),
                    'overdue_count' => (int) $row->overdue_count,
                ])
                ->all(),
            'emis_due_soon_by_company' => self::emisDueSoonByCompany($tenantIds),
        ];
    }

    /**
     * @param  list<string>  $tenantIds
     * @return Builder<Invoice>
     */
    private static function openInvoices(array $tenantIds): Builder
    {
        return SuperAdminPortfolioScope::apply(Invoice::query(), $tenantIds)
            ->whereNotIn('invoices.payment_status', [
                InvoicePaymentStatus::Paid->value,
                InvoicePaymentStatus::Cancelled->value,
            ]);
    }

    /**
     * @param  list<string>  $tenantIds
     * @param  Collection<string, string>  $companyNames
     * @return list<array<string, mixed>>
     */
    private static function topOutstandingCustomers(array $tenantIds, Collection $companyNames, string $today): array
    {
        $rows = self::openInvoices($tenantIds)
            ->groupBy('invoices.tenant_id', 'invoices.customer_id')
            ->selectRaw(
                'invoices.tenant_id,
                invoices.customer_id,
                COUNT(*) as invoices_count,
                COALESCE(SUM(invoices.total_amount - invoices.paid_amount), 0) as outstanding,
                COALESCE(SUM(CASE WHEN DATE(invoices.due_date) < ? THEN invoices.total_amount - invoices.paid_amount ELSE 0 END), 0) as overdue',
                [$today],
            )
            ->orderByDesc('outstanding')
            ->limit(self::TOP_LIMIT)
            ->toBase()
            ->get();

        $customerNames = Customer::query()
            ->withoutGlobalScope('tenant')
            ->withTrashed()
            ->whereIn('id', $rows->pluck('customer_id')->all())
            ->pluck('name', 'id');

        return $rows
            ->map(static fn (object $row): array => [
                'customer_id' => (int) $row->customer_id,
                'name' => $customerNames->get($row->customer_id) ?? 'Unknown customer',
                'company_id' => (string) $row->tenant_id,
                'company_name' => $companyNames->get((string) $row->tenant_id),
                'invoices_count' => (int) $row->invoices_count,
                'outstanding' => round((float) $row->outstanding, 2),
                'overdue' => round((float) $row->overdue, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * Unpaid EMIs that are overdue or due within the next few days, counted per company.
     *
     * @param  list<string>  $tenantIds
     * @return array<string, int>
     */
    private static function emisDueSoonByCompany(array $tenantIds): array
    {
        return SuperAdminPortfolioScope::apply(Emi::query(), $tenantIds)
            ->where('emis.status', self::EMI_DUE_STATUS)
            ->whereDate('emis.due_date', '<=', Carbon::today()->addDays(self::EMI_DUE_SOON_DAYS))
            ->groupBy('emis.tenant_id')
            ->selectRaw('emis.tenant_id, COUNT(*) as due_soon_count')
            ->toBase()
            ->pluck('due_soon_count', 'tenant_id')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }
}
