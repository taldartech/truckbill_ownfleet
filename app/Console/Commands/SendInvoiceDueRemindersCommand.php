<?php

namespace App\Console\Commands;

use App\AppNotification;
use App\Enums\InvoicePaymentStatus;
use App\Invoice;
use App\User;
use App\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendInvoiceDueRemindersCommand extends Command
{
    protected $signature = 'invoices:send-due-reminders {--days=0 : Include invoices due within N days (0 = due today or overdue)}';

    protected $description = 'Notify company admins about unpaid invoices that are due or overdue';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $cutoff = now()->addDays($days)->toDateString();

        $invoices = Invoice::query()
            ->withoutGlobalScopes()
            ->with(['customer:id,name', 'tenant:id,name,owner_user_id'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $cutoff)
            ->whereIn('payment_status', [
                InvoicePaymentStatus::Pending->value,
                InvoicePaymentStatus::Partial->value,
            ])
            ->orderBy('due_date')
            ->get();

        if ($invoices->isEmpty()) {
            $this->info('No due invoices found.');

            return self::SUCCESS;
        }

        $notified = 0;

        foreach ($invoices as $invoice) {
            $recipients = User::query()
                ->where('tenant_id', $invoice->tenant_id)
                ->whereIn('user_role_id', [UserRole::ADMIN, UserRole::ACCOUNTANT, UserRole::SUPER_ADMIN])
                ->get();

            if ($recipients->isEmpty() && $invoice->tenant?->owner_user_id) {
                $owner = User::query()->find($invoice->tenant->owner_user_id);
                if ($owner !== null) {
                    $recipients = collect([$owner]);
                }
            }

            if ($recipients->isEmpty()) {
                Log::info('Invoice due reminder skipped: no recipients', [
                    'invoice_id' => $invoice->id,
                    'tenant_id' => $invoice->tenant_id,
                ]);

                continue;
            }

            $dueDate = $invoice->due_date?->toDateString() ?? '—';
            $title = 'Invoice payment due';
            $body = sprintf(
                'Invoice %s for %s is due on %s (remaining ₹%s).',
                $invoice->invoice_number,
                $invoice->customer?->name ?? 'customer',
                $dueDate,
                number_format($invoice->remainingAmount(), 2),
            );

            foreach ($recipients as $recipient) {
                $dedupeKey = sprintf('invoice-due:%s:%s:%s', $invoice->id, $recipient->id, $dueDate);

                $exists = AppNotification::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $invoice->tenant_id)
                    ->where('dedupe_key', $dedupeKey)
                    ->exists();

                if ($exists) {
                    continue;
                }

                AppNotification::query()->withoutGlobalScopes()->create([
                    'tenant_id' => $invoice->tenant_id,
                    'branch_id' => $invoice->branch_id,
                    'user_id' => $recipient->id,
                    'type' => 'invoice_due_reminder',
                    'title' => $title,
                    'body' => $body,
                    'data' => [
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number,
                        'due_date' => $dueDate,
                        'total_amount' => (float) $invoice->total_amount,
                        'remaining_amount' => $invoice->remainingAmount(),
                    ],
                    'dedupe_key' => $dedupeKey,
                ]);

                $notified++;
            }
        }

        $this->info("Created {$notified} reminder notification(s).");

        return self::SUCCESS;
    }
}
