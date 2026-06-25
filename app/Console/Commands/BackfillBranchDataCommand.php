<?php

namespace App\Console\Commands;

use App\Support\BranchProvisioner;
use App\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillBranchDataCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'branches:backfill-data {--dry-run : Report changes without writing}';

    /**
     * @var string
     */
    protected $description = 'Ensure default branches exist and backfill branch_id on operational records';

    /**
     * @var list<string>
     */
    private const TABLES = [
        'vehicles',
        'drivers',
        'driver_assignments',
        'attendances',
        'trips',
        'trip_route_legs',
        'trip_expenses',
        'trip_freights',
        'trip_status_histories',
        'challans',
        'tyres',
        'tyre_installations',
        'tyre_rotations',
        'tyre_damages',
        'tyre_replacements',
        'tyre_expenses',
        'vehicle_documents',
        'document_alert_settings',
        'brokers',
        'customers',
        'stations',
        'emis',
        'driver_advance_entries',
        'monthly_driver_wages',
        'attendance_month_locks',
        'app_notifications',
        'notification_logs',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run only — no data will be written.');
        }

        $branchByTenant = [];

        Tenant::query()->orderBy('created_at')->each(function (Tenant $tenant) use (&$branchByTenant, $dryRun): void {
            $branch = BranchProvisioner::ensureDefaultBranch($tenant);
            $branchByTenant[$tenant->id] = $branch->id;

            $this->line("Tenant {$tenant->name} → branch #{$branch->id} ({$branch->branch_name})");
        });

        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'branch_id')) {
                continue;
            }

            if (! Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            foreach ($branchByTenant as $tenantId => $branchId) {
                $query = DB::table($tableName)
                    ->where('tenant_id', $tenantId)
                    ->whereNull('branch_id');

                $count = (clone $query)->count();

                if ($count === 0) {
                    continue;
                }

                $this->line("{$tableName}: {$count} rows for tenant {$tenantId} → branch {$branchId}");

                if (! $dryRun) {
                    $query->update(['branch_id' => $branchId]);
                }
            }
        }

        $this->info($dryRun ? 'Dry run complete.' : 'Branch backfill complete.');

        return self::SUCCESS;
    }
}
