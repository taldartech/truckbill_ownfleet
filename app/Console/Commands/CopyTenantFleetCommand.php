<?php

namespace App\Console\Commands;

use App\Branch;
use App\Driver;
use App\DriverAssignment;
use App\Tenant;
use App\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

class CopyTenantFleetCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'fleet:copy-tenant';

    /**
     * @var string
     */
    protected $description = 'Interactively copy trucks and drivers from one company to another';

    public function handle(): int
    {
        $companies = Tenant::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        if ($companies->count() < 2) {
            $this->error('At least two companies are required to copy fleet data.');

            return self::FAILURE;
        }

        $sourceId = select(
            label: 'Source company',
            options: $this->companyOptions($companies),
            scroll: 15,
        );

        $source = $companies->firstWhere('id', $sourceId);
        $targetOptions = $this->companyOptions($companies->where('id', '!=', $sourceId)->values());

        $targetId = select(
            label: 'Target company',
            options: $targetOptions,
            scroll: 15,
        );

        $target = $companies->firstWhere('id', $targetId);

        if ($source === null || $target === null) {
            $this->error('Unable to resolve the selected companies.');

            return self::FAILURE;
        }

        try {
            $branchMap = $this->promptBranchMap($source, $target);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $withAssignments = confirm(
            label: 'Also copy current primary driver–truck assignments?',
            default: true,
        );

        $dryRun = confirm(
            label: 'Run as dry run (preview only, no writes)?',
            default: true,
        );

        $this->newLine();
        $this->info(sprintf('Source: %s', $source->name));
        $this->info(sprintf('Target: %s', $target->name));

        foreach ($branchMap as $sourceBranchId => $targetBranchId) {
            $this->line("Branch map: {$sourceBranchId} → {$targetBranchId}");
        }

        $this->line('Assignments: '.($withAssignments ? 'yes' : 'no'));
        $this->line('Mode: '.($dryRun ? 'dry run' : 'write'));
        $this->newLine();

        if (! confirm(label: 'Proceed with fleet copy?', default: ! $dryRun ? false : true)) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $stats = [
            'vehicles_copied' => 0,
            'vehicles_skipped' => 0,
            'drivers_copied' => 0,
            'drivers_skipped' => 0,
            'assignments_copied' => 0,
            'assignments_skipped' => 0,
        ];

        try {
            DB::transaction(function () use (
                $source,
                $target,
                $branchMap,
                $dryRun,
                $withAssignments,
                &$stats,
            ): void {
                [$vehicleMap, $vehicleStats] = $this->copyVehicles($source->id, $target->id, $branchMap, $dryRun);
                [$driverMap, $driverStats] = $this->copyDrivers($source->id, $target->id, $branchMap, $dryRun);

                $stats = array_merge($stats, $vehicleStats, $driverStats);

                if (! $withAssignments) {
                    return;
                }

                $assignmentStats = $this->copyCurrentAssignments(
                    $source->id,
                    $target->id,
                    $branchMap,
                    $vehicleMap,
                    $driverMap,
                    $dryRun,
                );

                $stats = array_merge($stats, $assignmentStats);
            });
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Count'],
            collect($stats)->map(fn (int $count, string $metric): array => [$metric, $count])->values()->all(),
        );

        $this->info($dryRun ? 'Dry run complete.' : 'Fleet copy complete.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Tenant>  $companies
     * @return array<string, string>
     */
    private function companyOptions(Collection $companies): array
    {
        return $companies
            ->mapWithKeys(fn (Tenant $tenant): array => [
                $tenant->id => sprintf('%s (%s)', $tenant->name, $tenant->slug),
            ])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function promptBranchMap(Tenant $source, Tenant $target): array
    {
        $sourceBranches = Branch::query()
            ->where('company_id', $source->id)
            ->orderByDesc('head_office')
            ->orderBy('id')
            ->get(['id', 'branch_name', 'head_office']);

        $targetBranches = Branch::query()
            ->where('company_id', $target->id)
            ->orderByDesc('head_office')
            ->orderBy('id')
            ->get(['id', 'branch_name', 'head_office']);

        if ($sourceBranches->isEmpty()) {
            throw new RuntimeException("Source company \"{$source->name}\" has no branches.");
        }

        if ($targetBranches->isEmpty()) {
            throw new RuntimeException("Target company \"{$target->name}\" has no branches.");
        }

        $targetOptions = $targetBranches
            ->mapWithKeys(fn (Branch $branch): array => [
                (string) $branch->id => $this->branchLabel($branch),
            ])
            ->all();

        $branchMap = [];

        foreach ($sourceBranches as $sourceBranch) {
            $default = array_key_first($targetOptions);

            $targetBranchId = select(
                label: sprintf('Map source branch "%s" to target branch', $this->branchLabel($sourceBranch)),
                options: $targetOptions,
                default: $default,
                scroll: 15,
            );

            $branchMap[(int) $sourceBranch->id] = (int) $targetBranchId;
        }

        return $branchMap;
    }

    private function branchLabel(Branch $branch): string
    {
        $name = filled($branch->branch_name) ? $branch->branch_name : 'Unnamed branch';
        $suffix = $branch->head_office ? ' [HO]' : '';

        return sprintf('#%d %s%s', $branch->id, $name, $suffix);
    }

    /**
     * @param  array<int, int>  $branchMap
     * @return array{0: array<string, string>, 1: array{vehicles_copied: int, vehicles_skipped: int}}
     */
    private function copyVehicles(string $sourceTenantId, string $targetTenantId, array $branchMap, bool $dryRun): array
    {
        $vehicleMap = [];
        $copied = 0;
        $skipped = 0;

        $vehicles = Vehicle::withoutGlobalScopes()
            ->where('tenant_id', $sourceTenantId)
            ->whereNull('deleted_at')
            ->orderBy('registration_number')
            ->get();

        foreach ($vehicles as $vehicle) {
            $sourceBranchId = (int) $vehicle->branch_id;

            if (! array_key_exists($sourceBranchId, $branchMap)) {
                throw new RuntimeException(
                    "Missing branch map for vehicle {$vehicle->registration_number} (branch {$sourceBranchId}).",
                );
            }

            $existing = Vehicle::withoutGlobalScopes()
                ->where('tenant_id', $targetTenantId)
                ->where('registration_number', $vehicle->registration_number)
                ->first();

            if ($existing !== null) {
                $vehicleMap[$vehicle->id] = $existing->id;
                $skipped++;

                continue;
            }

            $copied++;

            if ($dryRun) {
                continue;
            }

            $copy = $vehicle->replicate([
                'id',
                'tenant_id',
                'branch_id',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
                'deleted_at',
            ]);

            $copy->tenant_id = $targetTenantId;
            $copy->branch_id = $branchMap[$sourceBranchId];
            $copy->created_by = null;
            $copy->updated_by = null;
            $copy->save();

            $vehicleMap[$vehicle->id] = $copy->id;
        }

        return [
            $vehicleMap,
            [
                'vehicles_copied' => $copied,
                'vehicles_skipped' => $skipped,
            ],
        ];
    }

    /**
     * @param  array<int, int>  $branchMap
     * @return array{0: array<string, string>, 1: array{drivers_copied: int, drivers_skipped: int}}
     */
    private function copyDrivers(string $sourceTenantId, string $targetTenantId, array $branchMap, bool $dryRun): array
    {
        $driverMap = [];
        $copied = 0;
        $skipped = 0;

        $drivers = Driver::withoutGlobalScopes()
            ->where('tenant_id', $sourceTenantId)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        foreach ($drivers as $driver) {
            $sourceBranchId = (int) $driver->branch_id;

            if (! array_key_exists($sourceBranchId, $branchMap)) {
                throw new RuntimeException(
                    "Missing branch map for driver {$driver->name} (branch {$sourceBranchId}).",
                );
            }

            $existingQuery = Driver::withoutGlobalScopes()
                ->where('tenant_id', $targetTenantId)
                ->whereNull('deleted_at');

            if (filled($driver->license_number)) {
                $existingQuery->where('license_number', $driver->license_number);
            } else {
                $existingQuery
                    ->where('name', $driver->name)
                    ->where('phone', $driver->phone);
            }

            $existing = $existingQuery->first();

            if ($existing !== null) {
                $driverMap[$driver->id] = $existing->id;
                $skipped++;

                continue;
            }

            $copied++;

            if ($dryRun) {
                continue;
            }

            $copy = $driver->replicate([
                'id',
                'tenant_id',
                'branch_id',
                'created_by',
                'updated_by',
                'deleted_by',
                'created_at',
                'updated_at',
                'deleted_at',
            ]);

            $copy->tenant_id = $targetTenantId;
            $copy->branch_id = $branchMap[$sourceBranchId];
            $copy->created_by = null;
            $copy->updated_by = null;
            $copy->deleted_by = null;
            $copy->save();

            $driverMap[$driver->id] = $copy->id;
        }

        return [
            $driverMap,
            [
                'drivers_copied' => $copied,
                'drivers_skipped' => $skipped,
            ],
        ];
    }

    /**
     * @param  array<int, int>  $branchMap
     * @param  array<string, string>  $vehicleMap
     * @param  array<string, string>  $driverMap
     * @return array{assignments_copied: int, assignments_skipped: int}
     */
    private function copyCurrentAssignments(
        string $sourceTenantId,
        string $targetTenantId,
        array $branchMap,
        array $vehicleMap,
        array $driverMap,
        bool $dryRun,
    ): array {
        $copied = 0;
        $skipped = 0;
        $today = now()->toDateString();

        $assignments = DriverAssignment::withoutGlobalScopes()
            ->where('tenant_id', $sourceTenantId)
            ->where('is_primary', true)
            ->whereDate('assigned_from', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('assigned_to')
                    ->orWhereDate('assigned_to', '>=', $today);
            })
            ->get();

        foreach ($assignments as $assignment) {
            $newVehicleId = $vehicleMap[$assignment->vehicle_id] ?? null;
            $newDriverId = $driverMap[$assignment->driver_id] ?? null;
            $sourceBranchId = (int) $assignment->branch_id;

            if ($newVehicleId === null || $newDriverId === null || ! array_key_exists($sourceBranchId, $branchMap)) {
                $skipped++;

                continue;
            }

            $alreadyAssigned = DriverAssignment::withoutGlobalScopes()
                ->where('tenant_id', $targetTenantId)
                ->where('vehicle_id', $newVehicleId)
                ->where('is_primary', true)
                ->whereDate('assigned_from', '<=', $today)
                ->where(function ($query) use ($today): void {
                    $query->whereNull('assigned_to')
                        ->orWhereDate('assigned_to', '>=', $today);
                })
                ->exists();

            if ($alreadyAssigned) {
                $skipped++;

                continue;
            }

            $copied++;

            if ($dryRun) {
                continue;
            }

            DriverAssignment::withoutGlobalScopes()->create([
                'tenant_id' => $targetTenantId,
                'branch_id' => $branchMap[$sourceBranchId],
                'vehicle_id' => $newVehicleId,
                'driver_id' => $newDriverId,
                'assigned_from' => $assignment->assigned_from?->toDateString() ?? $today,
                'assigned_to' => null,
                'is_primary' => true,
                'created_by' => null,
                'updated_by' => null,
            ]);
        }

        return [
            'assignments_copied' => $copied,
            'assignments_skipped' => $skipped,
        ];
    }
}
