<?php

namespace App\Modules\BatteryManagement\Services;

use App\Battery;
use App\BatteryInspection;
use App\BatteryInstallation;
use App\BatteryReplacement;
use App\Enums\BatteryStatus;
use App\Modules\BatteryManagement\Repositories\BatteryRepository;
use App\Vehicle;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BatteryService
{
    public function __construct(
        private readonly BatteryRepository $repository
    ) {}

    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?string $brand = null,
        ?string $vehicleId = null,
        ?string $warranty = null
    ): LengthAwarePaginator {
        return $this->repository->paginate($perPage, $search, $status, $brand, $vehicleId, $warranty);
    }

    /**
     * @return array{total: int, in_stock: int, installed: int, removed: int, under_inspection: int, returned: int, scrapped: int, warranty_expiring: int}
     */
    public function getCounts(): array
    {
        return $this->repository->getCounts();
    }

    /**
     * @return list<string>
     */
    public function getBrands(): array
    {
        return $this->repository->getBrands();
    }

    /**
     * @return Collection<int, Battery>
     */
    public function getInStockList(?string $brand = null): Collection
    {
        return $this->repository->getInStockList($brand);
    }

    /**
     * @return array{battery: Battery, life_summary: array<string, mixed>, replacements: Collection<int, BatteryReplacement>}
     */
    public function showWithLifecycle(string $id): array
    {
        $battery = $this->repository->findWithHistory($id);

        $totalKm = 0.0;
        foreach ($battery->installations as $installation) {
            if ($installation->running_km !== null) {
                $totalKm += (float) $installation->running_km;
            } elseif ($installation->removed_at === null && $installation->vehicle !== null) {
                $stint = max(0, (float) $installation->vehicle->odometer_km - (float) $installation->km_at_installation);
                $totalKm += $stint;
            }
        }

        $ageMonths = 0;
        if ($battery->purchase_date) {
            $ageMonths = max(0, (int) Carbon::parse($battery->purchase_date)->diffInMonths(now()));
        }

        $warrantyRemainingDays = null;
        if ($battery->warranty_valid_until) {
            $diff = now()->diffInDays(Carbon::parse($battery->warranty_valid_until), false);
            $warrantyRemainingDays = (int) $diff;
        }

        $active = $battery->activeInstallation;
        $expectedLifeMonths = (int) ($battery->expected_life_months ?? 0);
        $lastInspection = $battery->inspections->first();

        $lifeSummary = [
            'total_running_km' => round($totalKm, 2),
            'installations_count' => $battery->installations->count(),
            'age_months' => $ageMonths,
            'life_used_percent' => $expectedLifeMonths > 0 ? (int) round(min(100, $ageMonths / $expectedLifeMonths * 100)) : null,
            'warranty_remaining_days' => $warrantyRemainingDays,
            'is_in_warranty' => $warrantyRemainingDays !== null && $warrantyRemainingDays >= 0,
            'current_vehicle' => $active ? [
                'vehicle_id' => $active->vehicle_id,
                'registration_number' => $active->vehicle?->registration_number,
                'slot_name' => $active->slot_name,
                'installed_at' => $active->installed_at?->toDateString(),
            ] : null,
            'last_inspection' => $lastInspection ? [
                'inspected_at' => $lastInspection->inspected_at?->toDateString(),
                'condition' => $lastInspection->condition,
                'voltage_reading' => $lastInspection->voltage_reading !== null ? (float) $lastInspection->voltage_reading : null,
            ] : null,
        ];

        return [
            'battery' => $battery,
            'life_summary' => $lifeSummary,
            'replacements' => $this->repository->getReplacementHistory($battery->id),
        ];
    }

    public function create(array $data): Battery
    {
        $data['status'] = BatteryStatus::InStock->value;
        $data['created_by'] = Auth::id();

        if (empty($data['warranty_valid_until']) && ! empty($data['purchase_date']) && ! empty($data['warranty_months'])) {
            $data['warranty_valid_until'] = Carbon::parse($data['purchase_date'])
                ->addMonths((int) $data['warranty_months'])
                ->toDateString();
        }

        if (empty($data['voltage'])) {
            $data['voltage'] = '12V';
        }

        return $this->repository->create($data);
    }

    public function update(Battery $battery, array $data): Battery
    {
        $data['updated_by'] = Auth::id();

        $warrantyInputsChanged = array_key_exists('purchase_date', $data) || array_key_exists('warranty_months', $data);

        if (empty($data['warranty_valid_until']) && $warrantyInputsChanged) {
            $purchaseDate = $data['purchase_date'] ?? $battery->purchase_date?->toDateString();
            $warrantyMonths = array_key_exists('warranty_months', $data)
                ? (int) $data['warranty_months']
                : (int) $battery->warranty_months;

            $data['warranty_valid_until'] = $purchaseDate && $warrantyMonths > 0
                ? Carbon::parse($purchaseDate)->addMonths($warrantyMonths)->toDateString()
                : null;
        }

        return $this->repository->update($battery, $data);
    }

    public function scrap(Battery $battery, array $data): Battery
    {
        if ($battery->status === BatteryStatus::Installed || $battery->activeInstallation !== null) {
            throw ValidationException::withMessages([
                'battery' => ['Cannot scrap a battery that is currently installed on a vehicle. Please remove or replace it first.'],
            ]);
        }

        $data['status'] = BatteryStatus::Scrapped->value;
        $data['scrapped_at'] = $data['scrapped_at'] ?? now()->toDateString();
        $data['updated_by'] = Auth::id();

        return $this->repository->update($battery, $data);
    }

    public function delete(Battery $battery): bool
    {
        if ($battery->status === BatteryStatus::Installed || $battery->activeInstallation !== null) {
            throw ValidationException::withMessages([
                'battery' => ['Cannot delete a battery that is currently installed on a vehicle.'],
            ]);
        }

        return $this->repository->delete($battery);
    }

    public function install(Battery $battery, array $data): BatteryInstallation
    {
        return DB::transaction(function () use ($battery, $data): BatteryInstallation {
            $battery = Battery::query()->whereKey($battery->id)->lockForUpdate()->firstOrFail();

            if ($battery->status === BatteryStatus::Scrapped) {
                throw ValidationException::withMessages(['battery' => ['Scrapped batteries cannot be installed.']]);
            }

            if ($battery->status === BatteryStatus::Installed || $this->repository->findActiveInstallation($battery->id)) {
                throw ValidationException::withMessages(['battery' => ['Battery is already installed on a vehicle.']]);
            }

            $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
            $slotName = ! empty($data['slot_name']) ? trim($data['slot_name']) : 'Main #1';

            $occupied = $this->repository->findActiveInstallationOnSlot($vehicle->id, $slotName);
            if ($occupied) {
                throw ValidationException::withMessages([
                    'slot_name' => ["Slot '{$slotName}' on vehicle {$vehicle->registration_number} is already occupied. Use replace instead."],
                ]);
            }

            $installedAt = $data['installed_at'];
            if ($battery->purchase_date && Carbon::parse($installedAt)->lt(Carbon::parse($battery->purchase_date))) {
                throw ValidationException::withMessages([
                    'installed_at' => ['Installation date cannot be earlier than battery purchase date ('.$battery->purchase_date->toDateString().').'],
                ]);
            }

            $kmAtInstall = isset($data['km_at_installation']) && $data['km_at_installation'] !== ''
                ? (float) $data['km_at_installation']
                : (float) ($vehicle->odometer_km ?? 0);

            $actorId = Auth::id();

            $installation = $this->repository->createInstallation([
                'tenant_id' => $vehicle->tenant_id,
                'branch_id' => $vehicle->branch_id,
                'battery_id' => $battery->id,
                'vehicle_id' => $vehicle->id,
                'slot_name' => $slotName,
                'installed_at' => $installedAt,
                'km_at_installation' => $kmAtInstall,
                'notes' => $data['notes'] ?? null,
                'installed_by' => $actorId,
            ]);

            $this->repository->update($battery, [
                'status' => BatteryStatus::Installed->value,
            ]);

            if ($kmAtInstall > (float) ($vehicle->odometer_km ?? 0)) {
                $vehicle->update(['odometer_km' => $kmAtInstall]);
            }

            return $installation->load(['battery', 'vehicle']);
        });
    }

    public function replaceOnVehicle(array $data): BatteryReplacement
    {
        return DB::transaction(function () use ($data): BatteryReplacement {
            $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
            $oldBattery = Battery::query()->whereKey($data['old_battery_id'])->lockForUpdate()->firstOrFail();

            $activeInstallation = BatteryInstallation::query()
                ->where('battery_id', $oldBattery->id)
                ->where('vehicle_id', $vehicle->id)
                ->whereNull('removed_at')
                ->lockForUpdate()
                ->first();

            if (! $activeInstallation) {
                throw ValidationException::withMessages([
                    'old_battery_id' => ["Old battery {$oldBattery->serial_number} is not currently installed on vehicle {$vehicle->registration_number}."],
                ]);
            }

            $slotName = ! empty($data['slot_name']) ? trim($data['slot_name']) : $activeInstallation->slot_name;
            $replacedAt = $data['replaced_at'];
            $odometer = (float) $data['odometer_km'];
            $actorId = Auth::id();

            if ($activeInstallation->installed_at && Carbon::parse($replacedAt)->lt(Carbon::parse($activeInstallation->installed_at))) {
                throw ValidationException::withMessages([
                    'replaced_at' => ['Replacement date cannot be before installation date ('.$activeInstallation->installed_at->toDateString().').'],
                ]);
            }

            $newBattery = null;
            if (! empty($data['new_battery_id'])) {
                $newBattery = Battery::query()->whereKey($data['new_battery_id'])->lockForUpdate()->firstOrFail();

                if ($newBattery->status === BatteryStatus::Scrapped) {
                    throw ValidationException::withMessages([
                        'new_battery_id' => ['Cannot install a scrapped battery.'],
                    ]);
                }

                if ($newBattery->status === BatteryStatus::Installed || $this->repository->findActiveInstallation($newBattery->id)) {
                    throw ValidationException::withMessages([
                        'new_battery_id' => ["New battery {$newBattery->serial_number} is already installed on a vehicle."],
                    ]);
                }

                if ($newBattery->purchase_date && Carbon::parse($replacedAt)->lt(Carbon::parse($newBattery->purchase_date))) {
                    throw ValidationException::withMessages([
                        'replaced_at' => ['Replacement date cannot be earlier than new battery purchase date ('.$newBattery->purchase_date->toDateString().').'],
                    ]);
                }
            }

            $kmAtInstall = (float) ($activeInstallation->km_at_installation ?? 0);
            $this->ensureOdometerNotBelowInstallation($odometer, $kmAtInstall);
            $runningKm = $odometer - $kmAtInstall;

            // 1. Close active stint on old battery
            $activeInstallation->update([
                'removed_at' => $replacedAt,
                'km_at_removal' => $odometer,
                'running_km' => $runningKm,
                'removal_reason' => $data['reason'],
                'notes' => $data['notes'] ?? $activeInstallation->notes,
                'removed_by' => $actorId,
            ]);

            // 2. Mark old battery removed
            $this->repository->update($oldBattery, [
                'status' => BatteryStatus::Removed->value,
            ]);

            // 3. Install new battery if provided
            if ($newBattery) {
                $this->repository->createInstallation([
                    'tenant_id' => $vehicle->tenant_id,
                    'branch_id' => $vehicle->branch_id,
                    'battery_id' => $newBattery->id,
                    'vehicle_id' => $vehicle->id,
                    'slot_name' => $slotName,
                    'installed_at' => $replacedAt,
                    'km_at_installation' => $odometer,
                    'notes' => $data['notes'] ?? null,
                    'installed_by' => $actorId,
                ]);

                $this->repository->update($newBattery, [
                    'status' => BatteryStatus::Installed->value,
                ]);
            }

            // 4. Log replacement
            $replacement = $this->repository->createReplacement([
                'tenant_id' => $vehicle->tenant_id,
                'branch_id' => $vehicle->branch_id,
                'vehicle_id' => $vehicle->id,
                'slot_name' => $slotName,
                'old_battery_id' => $oldBattery->id,
                'new_battery_id' => $newBattery?->id,
                'replaced_at' => $replacedAt,
                'odometer_km' => $odometer,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $actorId,
            ]);

            // 5. Update vehicle odometer
            if ($odometer > (float) ($vehicle->odometer_km ?? 0)) {
                $vehicle->update(['odometer_km' => $odometer]);
            }

            return $replacement->load(['vehicle', 'oldBattery', 'newBattery']);
        });
    }

    public function removeInstallation(BatteryInstallation $installation, array $data): BatteryInstallation
    {
        return DB::transaction(function () use ($installation, $data): BatteryInstallation {
            if ($installation->removed_at !== null) {
                throw ValidationException::withMessages(['installation' => ['Battery is already removed from this vehicle.']]);
            }

            $vehicle = $installation->vehicle ?? Vehicle::query()->findOrFail($installation->vehicle_id);
            $battery = $installation->battery ?? Battery::query()->findOrFail($installation->battery_id);
            $removedAt = $data['removed_at'];

            if ($installation->installed_at && Carbon::parse($removedAt)->lt(Carbon::parse($installation->installed_at))) {
                throw ValidationException::withMessages([
                    'removed_at' => ['Removal date cannot be before installation date ('.$installation->installed_at->toDateString().').'],
                ]);
            }

            $odometer = isset($data['odometer_km']) && $data['odometer_km'] !== ''
                ? (float) $data['odometer_km']
                : (float) ($vehicle->odometer_km ?? 0);

            $kmAtInstall = (float) ($installation->km_at_installation ?? 0);
            $this->ensureOdometerNotBelowInstallation($odometer, $kmAtInstall);
            $runningKm = isset($data['running_km']) && $data['running_km'] !== ''
                ? (float) $data['running_km']
                : max(0, $odometer - $kmAtInstall);

            $actorId = Auth::id();

            $installation->update([
                'removed_at' => $removedAt,
                'km_at_removal' => $odometer,
                'running_km' => $runningKm,
                'removal_reason' => $data['removal_reason'],
                'notes' => $data['notes'] ?? $installation->notes,
                'removed_by' => $actorId,
            ]);

            $this->repository->update($battery, [
                'status' => BatteryStatus::Removed->value,
            ]);

            $this->repository->createReplacement([
                'tenant_id' => $vehicle->tenant_id,
                'branch_id' => $vehicle->branch_id,
                'vehicle_id' => $vehicle->id,
                'slot_name' => $installation->slot_name,
                'old_battery_id' => $battery->id,
                'new_battery_id' => null,
                'replaced_at' => $removedAt,
                'odometer_km' => $odometer,
                'reason' => $data['removal_reason'],
                'notes' => $data['notes'] ?? 'Battery removed from vehicle without immediate replacement',
                'created_by' => $actorId,
            ]);

            if ($odometer > (float) ($vehicle->odometer_km ?? 0)) {
                $vehicle->update(['odometer_km' => $odometer]);
            }

            return $installation->fresh(['battery', 'vehicle']);
        });
    }

    /**
     * @return array{active_batteries: Collection<int, BatteryInstallation>, history: Collection<int, BatteryInstallation>, replacements: Collection<int, BatteryReplacement>}
     */
    public function vehicleBatteries(Vehicle $vehicle): array
    {
        return $this->repository->getVehicleBatteries($vehicle->id);
    }

    public function addInspection(Battery $battery, array $data): BatteryInspection
    {
        if ($battery->status === BatteryStatus::Scrapped) {
            throw ValidationException::withMessages(['battery' => ['Scrapped batteries cannot be inspected.']]);
        }

        if ($battery->purchase_date && Carbon::parse($data['inspected_at'])->lt($battery->purchase_date)) {
            throw ValidationException::withMessages([
                'inspected_at' => ['Inspection date cannot be earlier than battery purchase date ('.$battery->purchase_date->toDateString().').'],
            ]);
        }

        $inspection = $this->repository->createInspection([
            'tenant_id' => $battery->tenant_id,
            'branch_id' => $battery->branch_id,
            'battery_id' => $battery->id,
            'vehicle_id' => $battery->activeInstallation?->vehicle_id,
            'inspected_at' => $data['inspected_at'],
            'voltage_reading' => $data['voltage_reading'] ?? null,
            'specific_gravity' => $data['specific_gravity'] ?? null,
            'condition' => $data['condition'],
            'action_taken' => $data['action_taken'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return $inspection->load('vehicle');
    }

    private function ensureOdometerNotBelowInstallation(float $odometer, float $kmAtInstall): void
    {
        if ($odometer < $kmAtInstall) {
            throw ValidationException::withMessages([
                'odometer_km' => ['Odometer reading cannot be lower than the reading at installation ('.number_format($kmAtInstall).' KM).'],
            ]);
        }
    }
}
