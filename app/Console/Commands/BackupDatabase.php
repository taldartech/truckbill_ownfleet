<?php

namespace App\Console\Commands;

use App\Mail\DatabaseBackupFailed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    private const RETENTION_HOURS = 24;

    protected $signature = 'app:dbbackup {--disk= : Destination storage disk} {--retention-minutes= : Retention period in minutes}';

    protected $aliases = ['backup:database'];

    protected $description = 'Create database backup, upload to S3 and remove backups older than 24 hours';

    public function handle(): int
    {
        $localPath = null;
        $sqlPath = null;
        $filename = null;

        try {
            $filename = now()->format('d-m-Y-H-i-s');
            $localDirectory = storage_path('app/backup');

            if (! is_dir($localDirectory)) {
                mkdir($localDirectory, 0755, true);
            }

            $sqlPath = $localDirectory.'/'.$filename.'.sql';
            $localPath = $localDirectory.'/'.$filename.'.sql.gz';

            $connection = config('database.default', 'mysql');
            $dbConfig = config("database.connections.{$connection}", []);

            if (($dbConfig['driver'] ?? '') !== 'mysql') {
                throw new \RuntimeException('The configured database connection is not MySQL.');
            }

            $port = $dbConfig['port'] ?? 3306;
            $portArg = $port ? ' --port='.escapeshellarg($port) : '';

            $password = $dbConfig['password'] ?? '';
            $passwordArg = ($password !== null && $password !== '')
                ? ' --password='.escapeshellarg($password)
                : " --password=''";

            $mysqldump = config('backup.mysqldump_path', 'mysqldump');

            $command = escapeshellcmd($mysqldump)
                .' --user='.escapeshellarg($dbConfig['username'] ?? 'root')
                .$passwordArg
                .' --host='.escapeshellarg($dbConfig['host'] ?? '127.0.0.1')
                .$portArg
                .' --single-transaction --quick --column-statistics=0 '
                .escapeshellarg($dbConfig['database'] ?? '')
                .' > '.escapeshellarg($sqlPath)
                .' && gzip '.escapeshellarg($sqlPath);

            $process = Process::timeout(3600)->run($command);

            if (! $process->successful()) {
                throw new \RuntimeException('Database dump failed: '.trim($process->errorOutput() ?: $process->output()));
            }

            if (! file_exists($localPath) || filesize($localPath) === 0) {
                throw new \RuntimeException('Database backup file was not created or is empty.');
            }

            $this->info('Database backup created: '.basename($localPath));

            $disk = $this->option('disk') ?: config('filesystems.backup.disk', config('backup.disk', 's3'));
            $backupPath = config('filesystems.backup.path', config('backup.path', 'database-backups'));
            $destinationPath = $backupPath.'/'.basename($localPath);

            $stream = fopen($localPath, 'r');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open local backup file.');
            }

            try {
                $uploaded = Storage::disk($disk)->put($destinationPath, $stream);
            } finally {
                fclose($stream);
            }

            if (! $uploaded) {
                throw new \RuntimeException("Failed to upload database backup to disk [{$disk}].");
            }

            $this->info("Database backup uploaded to {$disk}: {$destinationPath}");

            // Clean up local gzipped file on success
            if (file_exists($localPath)) {
                unlink($localPath);
                $localPath = null;
            }

            // Retention cleanup (supports minutes or hours)
            $retentionMinutes = $this->option('retention-minutes') !== null
                ? (int) $this->option('retention-minutes')
                : (int) config('backup.retention_minutes', 0);

            if ($retentionMinutes > 0) {
                $cutoff = now()->subMinutes($retentionMinutes)->timestamp;
            } else {
                $retentionHours = (int) config('backup.retention_hours', self::RETENTION_HOURS);
                $cutoff = now()->subHours($retentionHours)->timestamp;
            }

            $files = Storage::disk($disk)->files($backupPath);

            foreach ($files as $file) {
                try {
                    if (Storage::disk($disk)->lastModified($file) < $cutoff) {
                        Storage::disk($disk)->delete($file);
                        $this->info('Deleted old backup: '.basename($file));
                    }
                } catch (\Throwable $retentionEx) {
                    report($retentionEx);
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            $this->error('Database backup failed: '.$e->getMessage());

            // Preserve local file for disaster recovery
            if ($localPath && file_exists($localPath)) {
                $this->error('Local backup preserved at: '.$localPath);
            } elseif ($sqlPath && file_exists($sqlPath)) {
                $this->error('Local uncompressed backup preserved at: '.$sqlPath);
            }

            // Send failure notification email if configured
            $failureEmail = config('backup.failure_email');
            if ($failureEmail) {
                try {
                    Mail::to($failureEmail)->send(new DatabaseBackupFailed(
                        errorMessage: $e->getMessage(),
                        attachmentName: basename($localPath ?? $sqlPath ?? ($filename ? "{$filename}.sql.gz" : 'backup.sql.gz')),
                        generatedAt: now()->format('Y-m-d H:i:s'),
                        appName: config('app.name', 'Truckbill'),
                    ));
                    $this->line("Database backup failure notification sent to {$failureEmail}.");
                } catch (\Throwable $mailException) {
                    report($mailException);
                    $this->error('Failed to send database backup failure notification: '.$mailException->getMessage());
                }
            }

            return self::FAILURE;
        }
    }
}
