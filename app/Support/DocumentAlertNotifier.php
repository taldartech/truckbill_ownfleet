<?php

namespace App\Support;

use App\AppNotification;
use App\DocumentAlertSetting;
use App\Mail\DocumentExpiryReminderMail;
use App\NotificationLog;
use App\User;
use App\VehicleDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DocumentAlertNotifier
{
    private const DEFAULT_REMINDER_DAYS = 30;

    public function notifyForDocument(VehicleDocument $document): void
    {
        $document->loadMissing('vehicle:id,registration_number');

        if ($document->expiry_date === null || $document->tenant_id === null) {
            return;
        }

        $today = Carbon::today();
        $expiryDate = $document->expiry_date->copy()->startOfDay();
        $reminderDays = $document->reminder_days_before ?? self::DEFAULT_REMINDER_DAYS;
        $isExpired = $expiryDate->lt($today);
        $isExpiringSoon = ! $isExpired && $expiryDate->lte($today->copy()->addDays($reminderDays));

        if (! $isExpired && ! $isExpiringSoon) {
            return;
        }

        $settings = DocumentAlertSetting::query()
            ->withoutGlobalScope('tenant')
            ->firstOrCreate(
                ['tenant_id' => $document->tenant_id],
                [
                    'notify_live' => true,
                    'notify_email' => true,
                    'recipient_user_ids' => [],
                ],
            );

        $recipients = $this->resolveRecipients($settings, (string) $document->tenant_id);

        if ($recipients->isEmpty()) {
            return;
        }

        $documentTypeLabel = $document->document_type?->label() ?? (string) $document->document_type;
        $vehicleRegistration = $document->vehicle?->registration_number ?? 'Unknown truck';
        $status = $isExpired ? 'expired' : 'expiring';
        $daysRemaining = (int) $today->diffInDays($expiryDate, false);
        $dedupeDate = $today->toDateString();
        $dedupeKey = "document_expiry:{$document->id}:{$dedupeDate}";

        $title = $isExpired
            ? "{$documentTypeLabel} expired — {$vehicleRegistration}"
            : "{$documentTypeLabel} expiring — {$vehicleRegistration}";

        $body = $isExpired
            ? "{$documentTypeLabel} for {$vehicleRegistration} expired on {$expiryDate->format('d M Y')}."
            : "{$documentTypeLabel} for {$vehicleRegistration} expires on {$expiryDate->format('d M Y')} ({$daysRemaining} days left).";

        $payload = [
            'document_id' => $document->id,
            'vehicle_id' => $document->vehicle_id,
            'vehicle_registration' => $vehicleRegistration,
            'document_type' => $document->document_type?->value ?? (string) $document->document_type,
            'document_type_label' => $documentTypeLabel,
            'expiry_date' => $expiryDate->toDateString(),
            'status' => $status,
            'days_remaining' => $daysRemaining,
        ];

        foreach ($recipients as $recipient) {
            if ($settings->notify_live && $recipient->document_alerts_live) {
                $this->createLiveNotification(
                    (string) $document->tenant_id,
                    $recipient,
                    $title,
                    $body,
                    $payload,
                    "{$dedupeKey}:{$recipient->id}",
                );
            }

            if ($settings->notify_email && $recipient->document_alerts_email) {
                $this->sendEmailNotification(
                    (string) $document->tenant_id,
                    $recipient,
                    $title,
                    $payload,
                );
            }
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveRecipients(DocumentAlertSetting $settings, string $tenantId): Collection
    {
        return User::query()
            ->with('userRole.permissions')
            ->where('tenant_id', $tenantId)
            ->where(function ($query): void {
                $query
                    ->where('document_alerts_live', true)
                    ->orWhere('document_alerts_email', true);
            })
            ->get()
            ->filter(fn (User $user): bool => $user->hasPermission('vehicle_documents.view'))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createLiveNotification(
        string $tenantId,
        User $recipient,
        string $title,
        string $body,
        array $payload,
        string $dedupeKey,
    ): void {
        $exists = AppNotification::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('dedupe_key', $dedupeKey)
            ->exists();

        if ($exists) {
            return;
        }

        AppNotification::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $recipient->id,
            'type' => 'document_expiry',
            'title' => $title,
            'body' => $body,
            'data' => $payload,
            'dedupe_key' => $dedupeKey,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendEmailNotification(
        string $tenantId,
        User $recipient,
        string $subject,
        array $payload,
    ): void {
        $alreadySent = NotificationLog::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('channel', 'email')
            ->where('recipient', $recipient->email)
            ->where('subject', $subject)
            ->whereDate('sent_at', Carbon::today())
            ->exists();

        if ($alreadySent) {
            return;
        }

        try {
            Mail::to($recipient->email)->send(new DocumentExpiryReminderMail($recipient, $payload));

            NotificationLog::query()->create([
                'tenant_id' => $tenantId,
                'channel' => 'email',
                'recipient' => $recipient->email,
                'subject' => $subject,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'status' => 'SENT',
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            NotificationLog::query()->create([
                'tenant_id' => $tenantId,
                'channel' => 'email',
                'recipient' => $recipient->email,
                'subject' => $subject,
                'payload' => json_encode([
                    'payload' => $payload,
                    'error' => $exception->getMessage(),
                ], JSON_THROW_ON_ERROR),
                'status' => 'FAILED',
                'sent_at' => now(),
            ]);
        }
    }
}
