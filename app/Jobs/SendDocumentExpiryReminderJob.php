<?php

namespace App\Jobs;

use App\Mail\DocumentExpiryReminderMail;
use App\NotificationLog;
use App\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDocumentExpiryReminderJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $tenantId,
        public string $recipientUserId,
        public string $subject,
        public array $payload,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->tenantId}:{$this->recipientUserId}:{$this->subject}:".Carbon::today()->toDateString();
    }

    public function handle(): void
    {
        $recipient = User::query()->find($this->recipientUserId);

        if ($recipient === null || $recipient->email === null || $recipient->email === '') {
            return;
        }

        if (! $recipient->document_alerts_email) {
            return;
        }

        $alreadySent = NotificationLog::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantId)
            ->where('channel', 'email')
            ->where('recipient', $recipient->email)
            ->where('subject', $this->subject)
            ->whereDate('sent_at', Carbon::today())
            ->exists();

        if ($alreadySent) {
            return;
        }

        try {
            Mail::to($recipient->email)->send(new DocumentExpiryReminderMail($recipient, $this->payload));

            NotificationLog::query()->create([
                'tenant_id' => $this->tenantId,
                'channel' => 'email',
                'recipient' => $recipient->email,
                'subject' => $this->subject,
                'payload' => json_encode($this->payload, JSON_THROW_ON_ERROR),
                'status' => 'SENT',
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            NotificationLog::query()->create([
                'tenant_id' => $this->tenantId,
                'channel' => 'email',
                'recipient' => $recipient->email,
                'subject' => $this->subject,
                'payload' => json_encode([
                    'payload' => $this->payload,
                    'error' => $exception->getMessage(),
                ], JSON_THROW_ON_ERROR),
                'status' => 'FAILED',
                'sent_at' => now(),
            ]);

            throw $exception;
        }
    }
}
