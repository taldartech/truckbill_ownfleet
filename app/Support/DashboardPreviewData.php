<?php

namespace App\Support;

class DashboardPreviewData
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        $operationalStats = [
            'lrs_pending_invoice' => 0,
            'eway_bills_expiring_today' => 0,
            'expired_eway_bills' => 0,
            'outstanding_amount' => 0,
        ];

        return [
            'monthlyStats' => [
                'total_bookings' => 0,
                'active_trips' => 0,
                'pending_lrs' => 0,
                'delivered_today' => 0,
                'lrs_pending_invoice' => 0,
                'eway_bills_expiring_today' => 0,
                'expired_eway_bills' => 0,
                'outstanding_amount' => 0,
            ],
            'systemStats' => [
                'monthly_revenue' => 0,
                'total_companies' => 0,
                'active_companies' => 0,
                'expired_plans' => 0,
                'upcoming_renewals' => 0,
                'total_users' => 0,
            ],
            'adminStats' => [
                'monthly_revenue' => 0,
                'total_companies' => 0,
                'active_companies' => 0,
                'expired_plans' => 0,
                'upcoming_renewals' => 0,
                'total_users' => 0,
                'outstanding_amount' => 0,
            ],
            'operationalStats' => $operationalStats,
            'moderatorStats' => [
                'incoming_bookings' => 0,
                'loading_in_progress' => 0,
                'en_route' => 0,
                'delayed_trips' => 0,
            ],
            'financialStats' => [
                'total_revenue' => 0,
                'total_invoiced' => 0,
                'pending_payments' => 0,
                'total_paid' => 0,
                'overdue_invoices' => 0,
            ],
            'transporterStats' => [
                'total_bookings' => 0,
                'active_trips' => 0,
                'pending_lrs' => 0,
                'delivered_today' => 0,
                'payments_received' => 0,
                'advance_received' => 0,
                'expired_eway_bills' => 0,
            ],
            'customerStats' => [
                'total_lorry_receipts' => 0,
                'pending_lorry_receipts' => 0,
                'delivered_lorry_receipts' => 0,
                'pending_invoices' => 0,
                'outstanding_amount' => 0,
            ],
            'expiredEwayBills' => collect(),
            'pendingInvoices' => collect(),
            'invoicePaymentStatuses' => [
                'pending' => 0,
                'partial' => 0,
                'paid' => 0,
            ],
        ];
    }
}
