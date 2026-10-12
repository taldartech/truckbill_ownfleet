<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('compliance:check-document-expiry --no-interaction')
    ->dailyAt('08:00')
    ->timezone('Asia/Kolkata');

Schedule::command('compliance:check-emi-due --no-interaction')
    ->dailyAt('08:05')
    ->timezone('Asia/Kolkata');

Schedule::command('compliance:check-challan-pending --no-interaction')
    ->dailyAt('08:10')
    ->timezone('Asia/Kolkata');

Schedule::command('invoices:send-due-reminders --no-interaction')
    ->dailyAt('08:15')
    ->timezone('Asia/Kolkata');

Schedule::command('subscription:apply-upcoming')
    ->dailyAt('00:15')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Apply pending upcoming subscriptions after term end');

Schedule::command('app:dbbackup')
    ->everyThreeHours()
    ->timezone(config('backup.schedule_timezone', 'Asia/Kolkata'))
    ->withoutOverlapping()
    ->description('Create MySQL backup, upload to S3 and remove backups older than 24 hours');
