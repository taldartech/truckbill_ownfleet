<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use App\Mail\DatabaseBackupFailed;

class BackupDatabase extends Command
{
    private const RETENTION_HOURS = 24;

    protected $signature = 'backup:database';

    protected $description = 'Backup MySQL database and remove backups older than the retention period';

    public function handle(): int
    {
        $timestamp = now()->format('Y-m-d_H-i-s');

        $filename = "database-backup-{$timestamp}.sql.gz";

        $localDirectory = storage_path('app/backups');

        $localPath = "{$localDirectory}/{$filename}";

        $diskName = config('backup.disk', 'local');

        $backupDirectory = config(
            'backup.path',
            'database-backups'
        );

        try {

            if (!File::exists($localDirectory)) {
                File::makeDirectory(
                    $localDirectory,
                    0755,
                    true
                );
            }

            /*
             * Step 1: Create MySQL backup locally.
             */
            $this->createMySqlBackup($localPath);

            if (
                !File::exists($localPath)
                || File::size($localPath) === 0
            ) {
                throw new \RuntimeException(
                    'Database backup file was not created or is empty.'
                );
            }

            /*
             * Step 2: Store backup on configured disk.
             */
            $disk = Storage::disk($diskName);

            $backupPath = "{$backupDirectory}/{$filename}";

            $stream = fopen($localPath, 'rb');

            if ($stream === false) {
                throw new \RuntimeException(
                    'Unable to open database backup for storage.'
                );
            }

            try {
                $uploaded = $disk->put(
                    $backupPath,
                    $stream
                );
            } finally {
                fclose($stream);
            }


            if (!$uploaded) {
                throw new \RuntimeException(
                    'Failed to store database backup.'
                );
            }

            /*
             * Step 3: Remove backups older than 24 hours.
             */
            $this->deleteExpiredBackups(
                $diskName,
                $backupDirectory
            );

            /*
             * Step 4: Remove temporary local file.
             */
            File::delete($localPath);

            $this->info(
                "Database backup completed successfully: {$filename}"
            );

            return self::SUCCESS;

        } catch (\Throwable $exception) {
            if (File::exists($localPath)) {
                File::delete($localPath);
            }

            report($exception);

            $this->error(
                'Database backup failed: ' . $exception->getMessage()
            );

            $failureEmail = config('backup.failure_email');

            if ($failureEmail) {
                try {
                    Mail::to($failureEmail)->send(
                        new DatabaseBackupFailed(
                            errorMessage: $exception->getMessage(),
                            attachmentName: $filename,
                            generatedAt: now()->format('Y-m-d H:i:s'),
                            appName: config('app.name', 'Truckbill'),
                        )
                    );

                    $this->line(
                        "Database backup failure notification sent to {$failureEmail}."
                    );
                } catch (\Throwable $mailException) {
                    report($mailException);

                    $this->error(
                        'Failed to send database backup failure notification: '
                        . $mailException->getMessage()
                    );
                }
            }

            return self::FAILURE;
        }
    }

    private function createMySqlBackup(
        string $backupPath
    ): void {
        $connection = config('database.default');

        $database = config(
            "database.connections.{$connection}"
        );

        if ($database['driver'] !== 'mysql') {
            throw new \RuntimeException(
                'The configured database connection is not MySQL.'
            );
        }

        $mysqldump = config(
            'backup.mysqldump_path',
            'mysqldump'
        );

        /*
         * Temporary SQL file.
         */
        $sqlPath = preg_replace(
            '/\.gz$/',
            '',
            $backupPath
        );

        $command = [
            $mysqldump,
            '--host=' . $database['host'],
            '--port=' . ($database['port'] ?? 3306),
            '--user=' . $database['username'],
            '--single-transaction',
            '--quick',
            '--routines',
            '--events',
            '--databases',
            $database['database'],
            '--result-file=' . $sqlPath,
        ];

        $process = new Process($command);

        $process->setEnv([
            'MYSQL_PWD' => $database['password'],
        ]);

        $process->setTimeout(3600);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                trim(
                    $process->getErrorOutput()
                    ?: $process->getOutput()
                )
            );
        }

        if (
            !File::exists($sqlPath)
            || File::size($sqlPath) === 0
        ) {
            throw new \RuntimeException(
                'MySQL dump was not created or is empty.'
            );
        }

        /*
         * Compress the SQL file in chunks.
         */
        $source = fopen($sqlPath, 'rb');

        $destination = gzopen(
            $backupPath,
            'wb9'
        );

        if (
            $source === false
            || $destination === false
        ) {
            throw new \RuntimeException(
                'Unable to compress the database backup.'
            );
        }

        while (!feof($source)) {
            $chunk = fread(
                $source,
                1024 * 1024
            );

            if ($chunk === false) {
                break;
            }

            gzwrite(
                $destination,
                $chunk
            );
        }

        fclose($source);

        gzclose($destination);

        /*
         * Delete temporary SQL file.
         */
        File::delete($sqlPath);
    }

    private function deleteExpiredBackups(
        string $diskName,
        string $directory
    ): void {
        $disk = Storage::disk($diskName);

        $retentionHours = (int) config(
            'backup.retention_hours',
            self::RETENTION_HOURS
        );

        $cutoff = now()->subHours(
            $retentionHours
        );

        foreach ($disk->files($directory) as $file) {

            if (
                !str_starts_with(
                    basename($file),
                    'database-backup-'
                )
            ) {
                continue;
            }

            $lastModified = Carbon::createFromTimestamp(
                $disk->lastModified($file)
            );

            if ($lastModified->lt($cutoff)) {

                $disk->delete($file);

                $this->line(
                    "Deleted expired backup: {$file}"
                );
            }
        }
    }
}
