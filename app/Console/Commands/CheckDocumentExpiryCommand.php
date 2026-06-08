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

    public function handle(DocumentAlertNotifier $notifier): int
    {
        $today = Carbon::today();
        $documents = VehicleDocument::query()
            ->withoutGlobalScope('tenant')
            ->with('vehicle:id,registration_number')
            ->whereNotNull('expiry_date')
            ->where(function ($query) use ($today): void {
                $query->whereDate('expiry_date', '<', $today->toDateString())
                    ->orWhereDate('expiry_date', '<=', $today->copy()->addDays(30)->toDateString());
            })
            ->get();

        $sentCount = 0;

        foreach ($documents as $document) {
            $notifier->notifyForDocument($document);
            $sentCount++;
        }

        $this->info("Processed {$sentCount} document alerts.");

        return self::SUCCESS;
    }
}
