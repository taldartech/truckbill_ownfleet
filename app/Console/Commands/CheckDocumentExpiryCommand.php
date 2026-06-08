<?php

namespace App\Console\Commands;

use App\Support\DocumentAlertNotifier;
use App\VehicleDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckDocumentExpiryCommand extends Command
{
    protected $signature = 'compliance:check-document-expiry';

    protected $description = 'Send live and email reminders for expiring vehicle documents';

    private const CHUNK_SIZE = 200;

    public function handle(DocumentAlertNotifier $notifier): int
    {
        $today = Carbon::today();
        $documentCount = 0;

        VehicleDocument::query()
            ->withoutGlobalScope('tenant')
            ->with('vehicle:id,registration_number')
            ->whereNotNull('expiry_date')
            ->where(function ($query) use ($today): void {
                $query->whereDate('expiry_date', '<', $today->toDateString())
                    ->orWhereDate('expiry_date', '<=', $today->copy()->addDays(30)->toDateString());
            })
            ->lazyById(self::CHUNK_SIZE)
            ->each(function (VehicleDocument $document) use ($notifier, &$documentCount): void {
                $notifier->notifyForDocument($document);
                $documentCount++;
            });

        $emailsQueued = $notifier->emailsQueued();

        $this->info("Processed {$documentCount} documents. Queued {$emailsQueued} email reminders.");

        return self::SUCCESS;
    }
}
