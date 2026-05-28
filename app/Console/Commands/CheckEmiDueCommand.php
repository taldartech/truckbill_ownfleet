<?php

namespace App\Console\Commands;

use App\Emi;
use App\Jobs\SendComplianceReminderJob;
use Illuminate\Console\Command;

class CheckEmiDueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:check-emi-due';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue reminders for due EMIs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $emis = Emi::query()
            ->whereDate('due_date', '<=', now()->addDays(3))
            ->where('status', 'DUE')
            ->get();

        foreach ($emis as $emi) {
            SendComplianceReminderJob::dispatch(
                (string) $emi->tenant_id,
                'email',
                'ops@tenant.local',
                'EMI due reminder',
                json_encode($emi->toArray(), JSON_THROW_ON_ERROR)
            );
        }

        $this->info("Queued {$emis->count()} EMI reminders.");

        return self::SUCCESS;
    }
}
