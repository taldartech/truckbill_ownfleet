<?php

namespace Tests\Feature\Console;

use App\Mail\DatabaseBackupFailed;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupDatabaseCommandTest extends TestCase
{
    private string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupDir = storage_path('app/backup');
        if (! is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }

        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'truckbill_test',
            'username' => 'root',
            'password' => 'secret',
        ]);
        Config::set('filesystems.backup.disk', 's3');
        Config::set('filesystems.backup.path', 'database-backups');
        Config::set('backup.failure_email', null);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        // Clean up any remaining backup files in storage/app/backup
        if (is_dir($this->backupDir)) {
            foreach (glob($this->backupDir.'/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        parent::tearDown();
    }

    public function test_successful_database_backup_and_upload(): void
    {
        Carbon::setTestNow('2026-10-24 10:00:00');
        Storage::fake('s3');

        Process::fake(function (\Illuminate\Process\PendingProcess $process) {
            $expectedFilename = now()->format('d-m-Y-H-i-s');
            $localGz = $this->backupDir.'/'.$expectedFilename.'.sql.gz';
            file_put_contents($localGz, 'compressed-sql-content');

            return Process::result();
        });

        $exitCode = Artisan::call('app:dbbackup', ['--disk' => 's3']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Database backup created: 24-10-2026-10-00-00.sql.gz', $output);
        $this->assertStringContainsString('Database backup uploaded to s3: database-backups/24-10-2026-10-00-00.sql.gz', $output);

        // Verify storage file exists
        Storage::disk('s3')->assertExists('database-backups/24-10-2026-10-00-00.sql.gz');

        // Verify local file was unlinked on success
        $this->assertFileDoesNotExist($this->backupDir.'/24-10-2026-10-00-00.sql.gz');
    }

    public function test_backup_with_alias_and_custom_options(): void
    {
        Carbon::setTestNow('2026-10-24 11:00:00');
        Storage::fake('backup_disk');

        // Test with empty password and 0 port
        Config::set('database.connections.mysql.password', '');
        Config::set('database.connections.mysql.port', 0);

        $commandExecuted = null;
        Process::fake(function (\Illuminate\Process\PendingProcess $process) use (&$commandExecuted) {
            $commandExecuted = $process->command;
            $localGz = $this->backupDir.'/'.now()->format('d-m-Y-H-i-s').'.sql.gz';
            file_put_contents($localGz, 'dummy-dump');

            return Process::result();
        });

        $exitCode = Artisan::call('backup:database', ['--disk' => 'backup_disk']);

        $this->assertSame(0, $exitCode);
        $this->assertNotNull($commandExecuted);
        $this->assertStringContainsString(" --password=''", $commandExecuted);
        $this->assertStringNotContainsString('--port=', $commandExecuted);
        Storage::disk('backup_disk')->assertExists('database-backups/24-10-2026-11-00-00.sql.gz');
    }

    public function test_retention_cleanup_with_minutes(): void
    {
        Carbon::setTestNow('2026-10-24 12:00:00');
        Storage::fake('s3');

        $disk = Storage::disk('s3');
        $oldFile = 'database-backups/old-backup.sql.gz';
        $recentFile = 'database-backups/recent-backup.sql.gz';

        $disk->put($oldFile, 'old');
        $disk->put($recentFile, 'recent');

        // Simulate last modified times using reflection / wrapper
        // In local/fake disk, touch file or set time
        $realPathOld = $disk->path($oldFile);
        $realPathRecent = $disk->path($recentFile);
        touch($realPathOld, now()->subMinutes(60)->timestamp);
        touch($realPathRecent, now()->subMinutes(10)->timestamp);

        Process::fake(function () {
            file_put_contents($this->backupDir.'/'.now()->format('d-m-Y-H-i-s').'.sql.gz', 'new-dump');

            return Process::result();
        });

        $exitCode = Artisan::call('app:dbbackup', [
            '--disk' => 's3',
            '--retention-minutes' => '30',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Deleted old backup: old-backup.sql.gz', $output);
        $this->assertFalse($disk->exists($oldFile));
        $this->assertTrue($disk->exists($recentFile));
    }

    public function test_retention_cleanup_with_default_hours(): void
    {
        Carbon::setTestNow('2026-10-24 12:00:00');
        Storage::fake('s3');

        $disk = Storage::disk('s3');
        $veryOldFile = 'database-backups/very-old.sql.gz';
        $recentFile = 'database-backups/recent.sql.gz';

        $disk->put($veryOldFile, 'very-old');
        $disk->put($recentFile, 'recent');

        touch($disk->path($veryOldFile), now()->subHours(30)->timestamp);
        touch($disk->path($recentFile), now()->subHours(5)->timestamp);

        Process::fake(function () {
            file_put_contents($this->backupDir.'/'.now()->format('d-m-Y-H-i-s').'.sql.gz', 'content');

            return Process::result();
        });

        $exitCode = Artisan::call('app:dbbackup', ['--disk' => 's3']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Deleted old backup: very-old.sql.gz', $output);
        $this->assertFalse($disk->exists($veryOldFile));
        $this->assertTrue($disk->exists($recentFile));
    }

    public function test_retention_exception_is_handled_gracefully(): void
    {
        Carbon::setTestNow('2026-10-24 12:00:00');
        Storage::fake('s3');

        $disk = Storage::disk('s3');
        $disk->put('database-backups/problematic.sql.gz', 'corrupt');

        Process::fake(function () {
            file_put_contents($this->backupDir.'/'.now()->format('d-m-Y-H-i-s').'.sql.gz', 'ok');

            return Process::result();
        });

        // Mock Storage driver to throw on lastModified
        $mockDisk = \Mockery::mock($disk)->makePartial();
        $mockDisk->shouldReceive('files')->andReturn(['database-backups/problematic.sql.gz']);
        $mockDisk->shouldReceive('lastModified')->andThrow(new \RuntimeException('Disk read error'));
        Storage::set('s3', $mockDisk);

        $exitCode = Artisan::call('app:dbbackup', ['--disk' => 's3']);

        $this->assertSame(0, $exitCode);
    }

    public function test_fails_when_driver_is_not_mysql(): void
    {
        Config::set('database.default', 'sqlite');

        $exitCode = Artisan::call('app:dbbackup');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('The configured database connection is not MySQL.', $output);
    }

    public function test_fails_when_process_fails(): void
    {
        Process::fake([
            '*' => Process::result(
                exitCode: 1,
                errorOutput: 'mysqldump: Got error 1045: Access denied',
            ),
        ]);

        $exitCode = Artisan::call('app:dbbackup');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Database dump failed: mysqldump: Got error 1045: Access denied', $output);
    }

    public function test_fails_when_backup_file_was_not_created(): void
    {
        Process::fake([
            '*' => Process::result(exitCode: 0),
        ]);

        $exitCode = Artisan::call('app:dbbackup');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Database backup file was not created or is empty.', $output);
    }

    public function test_fails_when_backup_file_is_empty(): void
    {
        Process::fake(function () {
            file_put_contents($this->backupDir.'/'.now()->format('d-m-Y-H-i-s').'.sql.gz', '');

            return Process::result(exitCode: 0);
        });

        $exitCode = Artisan::call('app:dbbackup');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Database backup file was not created or is empty.', $output);
    }

    public function test_fails_when_storage_upload_fails_and_preserves_local_gz_file(): void
    {
        Carbon::setTestNow('2026-10-24 14:00:00');
        Storage::fake('s3');

        $expectedGz = $this->backupDir.'/24-10-2026-14-00-00.sql.gz';

        Process::fake(function () use ($expectedGz) {
            file_put_contents($expectedGz, 'valid-archive-data');

            return Process::result();
        });

        // Mock Storage put to return false
        $disk = Storage::disk('s3');
        $mockDisk = \Mockery::mock($disk)->makePartial();
        $mockDisk->shouldReceive('put')->andReturn(false);
        Storage::set('s3', $mockDisk);

        $exitCode = Artisan::call('app:dbbackup', ['--disk' => 's3']);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Failed to upload database backup to disk [s3].', $output);
        $this->assertStringContainsString('Local backup preserved at: '.$expectedGz, $output);
        $this->assertFileExists($expectedGz);

        @unlink($expectedGz);
    }

    public function test_preserves_local_uncompressed_sql_when_dump_fails_after_sql_exists(): void
    {
        Carbon::setTestNow('2026-10-24 15:00:00');
        $sqlPath = $this->backupDir.'/24-10-2026-15-00-00.sql';
        file_put_contents($sqlPath, 'CREATE TABLE test (id INT);');

        Process::fake([
            '*' => Process::result(exitCode: 1, errorOutput: 'gzip failed: out of disk space'),
        ]);

        $exitCode = Artisan::call('app:dbbackup');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Local uncompressed backup preserved at: '.$sqlPath, $output);
        $this->assertFileExists($sqlPath);

        @unlink($sqlPath);
    }

    public function test_failure_notification_email_sent_when_configured(): void
    {
        Mail::fake();
        Config::set('backup.failure_email', 'devops@truckbill.in');

        Process::fake([
            '*' => Process::result(exitCode: 1, errorOutput: 'Connection timed out'),
        ]);

        $exitCode = Artisan::call('app:dbbackup');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Database backup failure notification sent to devops@truckbill.in.', $output);

        Mail::assertSent(DatabaseBackupFailed::class, function (DatabaseBackupFailed $mail) {
            return $mail->hasTo('devops@truckbill.in')
                && str_contains($mail->errorMessage, 'Connection timed out');
        });
    }

    public function test_failure_notification_email_handles_mail_exception(): void
    {
        Config::set('backup.failure_email', 'devops@truckbill.in');

        Process::fake([
            '*' => Process::result(exitCode: 1, errorOutput: 'Fatal error'),
        ]);

        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new \RuntimeException('SMTP Server Unavailable'));

        $exitCode = Artisan::call('app:dbbackup');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Failed to send database backup failure notification: SMTP Server Unavailable', $output);
    }

    public function test_failure_does_not_send_mail_when_no_email_configured(): void
    {
        Mail::fake();
        Config::set('backup.failure_email', null);

        Process::fake([
            '*' => Process::result(exitCode: 1, errorOutput: 'Fatal crash'),
        ]);

        $exitCode = Artisan::call('app:dbbackup');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringNotContainsString('Database backup failure notification sent', $output);
        Mail::assertNothingSent();
    }

    public function test_creates_directory_if_it_does_not_exist(): void
    {
        Carbon::setTestNow('2026-10-24 16:00:00');
        Storage::fake('s3');

        // Delete the backup directory if it exists
        if (is_dir($this->backupDir)) {
            foreach (glob($this->backupDir.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->backupDir);
        }

        $this->assertDirectoryDoesNotExist($this->backupDir);

        Process::fake(function () {
            file_put_contents($this->backupDir.'/'.now()->format('d-m-Y-H-i-s').'.sql.gz', 'data');

            return Process::result();
        });

        $exitCode = Artisan::call('app:dbbackup', ['--disk' => 's3']);
        $this->assertSame(0, $exitCode);
        $this->assertDirectoryExists($this->backupDir);
    }

    public function test_fails_when_local_backup_file_cannot_be_opened(): void
    {
        Carbon::setTestNow('2026-10-24 17:00:00');
        Storage::fake('s3');
        $expectedGz = $this->backupDir.'/24-10-2026-17-00-00.sql.gz';

        Process::fake(function () use ($expectedGz) {
            file_put_contents($expectedGz, 'valid-archive-data');
            chmod($expectedGz, 0000);

            return Process::result();
        });

        $exitCode = Artisan::call('app:dbbackup', ['--disk' => 's3']);
        $output = Artisan::output();

        chmod($expectedGz, 0644);
        @unlink($expectedGz);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Unable to open local backup file.', $output);
    }

    public function test_database_backup_failed_mailable_build(): void
    {
        $mailable = new DatabaseBackupFailed(
            errorMessage: 'Test error message',
            attachmentName: 'backup.sql.gz',
            generatedAt: '2026-10-24 18:00:00',
            appName: 'Truckbill',
        );

        $built = $mailable->build();
        $this->assertSame('Database Backup Failed', $built->subject);
        $this->assertSame('emails.database-backup-failed', $built->view);
    }
}
