<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $email,
        public string $token,
        public int $count,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset your TruckBill password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.reset_password',
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
