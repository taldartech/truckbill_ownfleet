<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DatabaseBackupFailed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $errorMessage,
        public readonly string $attachmentName,
        public readonly string $generatedAt,
        public readonly string $appName,
    ) {}

    public function build(): self
    {
        return $this
            ->subject('Database Backup Failed')
            ->view('emails.database-backup-failed');
    }
}