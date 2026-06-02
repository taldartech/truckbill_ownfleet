<?php

use Illuminate\Support\Facades\Route;

/**
 * Named routes expected by legacy TruckBill Blade views (sidebar, links).
 * Registers classic URLs so route() does not throw; clicks redirect to /ui/ previews.
 */
return function (): void {
    $register = static function (string $uri, string $name, string $uiPath): void {
        Route::get($uri, static fn () => redirect('/ui/'.$uiPath))->name($name);
    };

    Route::get('/company/exit', function () {
        \App\Support\SelectedCompanySession::forget();

        return redirect('/app/companies');
    })->name('company.exit');

    $routes = [
        'voucher_payments.daily' => ['daily-payments', 'voucher_payments/daily'],
        'voucher_payments.index' => ['voucher_payments', 'voucher_payments/index'],
        'master.truck-types.index' => ['master/truck-types', 'master/truck-types'],
        'master.summary-dashboard' => ['master/summary-dashboard', 'master/summary_dashboard'],
        'promo_codes.index' => ['promo_codes', 'promo_code/index'],
        'email_templates.index' => ['email_templates', 'email_templates/index'],
        'audit-logs.index' => ['audit-logs', 'audit_logs/index'],
        'voucher_templates.index' => ['voucher_templates', 'voucher_templates/index'],
        'company_bank_details.index' => ['company_bank_details', 'company_bank_details/index'],
        'payment.index' => ['payment', 'payment/index'],
        'transporter.freight_bookings.index' => ['transporter/freight-bookings', 'transporter/freight_bookings'],
        'transporter.payments.index' => ['transporter/payments', 'transporter/payments'],
        'customer.lorry-receipts.index' => ['customer/lorry-receipts', 'customer/lorry_receipts'],
        'customer.invoices.index' => ['customer/invoices', 'customer/invoices'],
        'customer.payments.index' => ['customer/payments', 'customer/payments'],
        'customer.transporters.index' => ['customer/transporters', 'customer/transporters'],
        'reports.tds' => ['reports/tds', 'reports/tds'],
        'reports.consignor-consignee' => ['reports/consignor-consignee', 'reports/consignor-consignee'],
        'reports.dispatch' => ['reports/dispatch', 'reports/dispatch'],
        'reports.party-ledger' => ['reports/party-ledger', 'reports/party-ledger'],
        'reports.lr-copy' => ['reports/lr-copy', 'reports/lr-copy'],
        'reports.invoice' => ['reports/invoice', 'reports/invoice'],
        'reports.shortage-damage' => ['reports/shortage-damage', 'reports/shortage-damage'],
        'reports.eway-bill' => ['reports/eway-bill', 'reports/eway-bill'],
        'reports.pump' => ['reports/pump', 'reports/pump'],
        'reports.bank' => ['reports/bank', 'reports/bank'],
        'reports.cash' => ['reports/cash', 'reports/cash'],
        'reports.party-bill' => ['reports/party-bill', 'reports/party-bill'],
        'reports.memo-copy' => ['reports/memo-copy', 'reports/memo-copy'],
        'reports.transporter-report' => ['reports/transporter-report', 'reports/transporter-report'],
        'reports.transporter-master' => ['reports/transporter-master', 'reports/transporter-master'],
        'reports.pending-memo' => ['reports/pending-memo', 'reports/pending-memo'],
        'reports.pending-invoice' => ['reports/pending-invoice', 'reports/pending-invoice'],
        'users.index' => ['users', 'users/index'],
        'users.create' => ['users/create', 'users/create'],
        'truck_owners.index' => ['truck_owners', 'truck_owner/index'],
        'truck_owners.create' => ['truck_owners/create', 'truck_owner/create'],
        'consignees.index' => ['consignees', 'consignees/index'],
        'consignees.create' => ['consignees/create', 'consignees/create'],
        'lorry_receipts.index' => ['lorry_receipts', 'lorry_receipts/index'],
        'lorry_receipts.create' => ['lorry_receipts/create', 'lorry_receipts/create'],
    ];

    foreach ($routes as $name => [$uri, $uiPath]) {
        $register($uri, $name, $uiPath);
    }
};
