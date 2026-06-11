<?php

namespace App\Support;

use App\AppNotification;
use App\DocumentAlertSetting;
use App\Jobs\SendDocumentExpiryReminderJob;
use App\User;
use App\VehicleDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DocumentAlertNotifier
{
    private const DEFAULT_REMINDER_DAYS = 30;

    /**
     * @var array<string, DocumentAlertSetting>
     */
    private array $settingsCache = [];

    /**
     * @var array<string, Collection<int, User>>
     */
    private array $recipientsCache = [];

    private int $emailsQueued = 0;

    public function emailsQueued(): int
    {
        return $this->emailsQueued;
    }

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

        $tenantId = (string) $document->tenant_id;
        $settings = $this->resolveSettings($tenantId);
        $recipients = $this->resolveRecipients($settings, $tenantId);

        if ($recipients->isEmpty()) {
            return;
        }

        $code = (string) $document->document_type;
        $enumType = \App\Enums\VehicleDocumentType::tryFrom($code);
        $documentTypeLabel = \App\Support\DocumentTypeDefaults::labelFor($tenantId, $code)
            ?? $enumType?->label()
            ?? $code;
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
            'document_type' => $code,
            'document_type_label' => $documentTypeLabel,
            'expiry_date' => $expiryDate->toDateString(),
            'status' => $status,
            'days_remaining' => $daysRemaining,
        ];

        foreach ($recipients as $recipient) {
            if ($settings->notify_live && $recipient->document_alerts_live) {
                $this->createLiveNotification(
                    $tenantId,
                    $recipient,
                    $title,
                    $body,
                    $payload,
                    "{$dedupeKey}:{$recipient->id}",
                );
            }

            if ($settings->notify_email && $recipient->document_alerts_email) {
                SendDocumentExpiryReminderJob::dispatch(
                    $tenantId,
                    (string) $recipient->id,
                    $title,
                    $payload,
                );

                $this->emailsQueued++;
            }
        }
    }

    private function resolveSettings(string $tenantId): DocumentAlertSetting
    {
        if (isset($this->settingsCache[$tenantId])) {
            return $this->settingsCache[$tenantId];
        }

        $this->settingsCache[$tenantId] = DocumentAlertSetting::query()
            ->withoutGlobalScope('tenant')
            ->firstOrCreate(
                ['tenant_id' => $tenantId],
                [
                    'notify_live' => true,
                    'notify_email' => true,
                    'recipient_user_ids' => [],
                ],
            );

        return $this->settingsCache[$tenantId];
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveRecipients(DocumentAlertSetting $settings, string $tenantId): Collection
    {
        if (isset($this->recipientsCache[$tenantId])) {
            return $this->recipientsCache[$tenantId];
        }

        $this->recipientsCache[$tenantId] = User::query()
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

        return $this->recipientsCache[$tenantId];
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
}
