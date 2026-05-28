<?php

namespace App\Console\Commands;

use App\Jobs\SendComplianceReminderJob;
use App\VehicleDocument;
use Illuminate\Console\Command;

class CheckDocumentExpiryCommand extends Command
{
    protected $signature = 'compliance:check-document-expiry';

    protected $description = 'Queue reminders for expiring vehicle documents';

    public function handle(): int
    {
        $documents = VehicleDocument::query()
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<=', now()->addDays(7))
            ->get();

        foreach ($documents as $document) {
            SendComplianceReminderJob::dispatch(
                (string) $document->tenant_id,
                'email',
                'ops@tenant.local',
                'Document expiry reminder',
                json_encode($document->toArray(), JSON_THROW_ON_ERROR)
            );
        }

        $this->info("Queued {$documents->count()} document reminders.");

        return self::SUCCESS;
    }
}
