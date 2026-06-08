<?php

namespace App\Mail;

use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentExpiryReminderMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public User $recipient,
        public array $payload,
    ) {}

    public function envelope(): Envelope
    {
        $vehicleRegistration = (string) ($this->payload['vehicle_registration'] ?? 'Truck');
        $documentTypeLabel = (string) ($this->payload['document_type_label'] ?? 'Document');
        $status = ($this->payload['status'] ?? 'expiring') === 'expired' ? 'expired' : 'expiring soon';

        return new Envelope(
            subject: "Document alert: {$documentTypeLabel} {$status} — {$vehicleRegistration}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-expiry-reminder',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
