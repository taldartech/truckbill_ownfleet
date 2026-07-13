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
