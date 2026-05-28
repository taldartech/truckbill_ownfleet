<?php

namespace App\Console\Commands;

use App\Challan;
use App\Jobs\SendComplianceReminderJob;
use Illuminate\Console\Command;

class CheckChallanPendingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:check-challan-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue reminders for pending challans';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $challans = Challan::query()
            ->where('status', 'PENDING')
            ->get();

        foreach ($challans as $challan) {
            SendComplianceReminderJob::dispatch(
                (string) $challan->tenant_id,
                'email',
                'ops@tenant.local',
                'Pending challan reminder',
                json_encode($challan->toArray(), JSON_THROW_ON_ERROR)
            );
        }

        $this->info("Queued {$challans->count()} challan reminders.");

        return self::SUCCESS;
    }
}
