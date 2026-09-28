<?php

namespace App\Jobs;

use App\NotificationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendComplianceReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tenantId,
        private readonly string $channel,
        private readonly string $recipient,
        private readonly string $subject,
        private readonly string $payload
    ) {}

    public function handle(): void
    {
        NotificationLog::query()->create([
            'tenant_id' => $this->tenantId,
            'channel' => $this->channel,
            'recipient' => $this->recipient,
            'subject' => $this->subject,
            'payload' => $this->payload,
            'status' => 'SENT',
            'sent_at' => now(),
        ]);
    }
}
