<?php

namespace App\Modules\BatteryManagement\Repositories;

use App\Battery;
use App\BatteryInspection;
use App\BatteryInstallation;
use App\BatteryReplacement;
use App\Enums\BatteryStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BatteryRepository
{
    /**
     * Paginate batteries with filtered conditions and pre-loaded active relations.
     */
    public function paginate(
        int $perPage = 15,
        ?string $search = null,
        ?string $status = null,
        ?string $brand = null,
        ?string $vehicleId = null,
        ?string $warranty = null
    ): LengthAwarePaginator {
        return $this->baseQuery($search, $status, $brand, $vehicleId, $warranty)
            ->with(['activeInstallation.vehicle'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get inventory counts in a single fast query for dashboard / tabs.
     *
     * @return array{total: int, in_stock: int, installed: int, removed: int, under_inspection: int, returned: int, scrapped: int, warranty_expiring: int}
     */
    public function getCounts(): array
    {
        $raw = Battery::query()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_stock,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as installed,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as `removed`,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as under_inspection,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as `returned`,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as scrapped,
                SUM(CASE WHEN status <> ? AND warranty_valid_until >= ? AND warranty_valid_until <= ? THEN 1 ELSE 0 END) as warranty_expiring
            ', [
                BatteryStatus::InStock->value,
                BatteryStatus::Installed->value,
                BatteryStatus::Removed->value,
                BatteryStatus::UnderInspection->value,
                BatteryStatus::Returned->value,
                BatteryStatus::Scrapped->value,
                BatteryStatus::Scrapped->value,
                now()->toDateString(),
                now()->addDays(Battery::WARRANTY_EXPIRING_DAYS)->toDateString(),
            ])
            ->first();

        return [
            'total' => (int) ($raw?->total ?? 0),
            'in_stock' => (int) ($raw?->in_stock ?? 0),
            'installed' => (int) ($raw?->installed ?? 0),
            'removed' => (int) ($raw?->removed ?? 0),
            'under_inspection' => (int) ($raw?->under_inspection ?? 0),
            'returned' => (int) ($raw?->returned ?? 0),
            'scrapped' => (int) ($raw?->scrapped ?? 0),
            'warranty_expiring' => (int) ($raw?->warranty_expiring ?? 0),
        ];
    }

    /**
     * Distinct brands in the current company's inventory, for filters and suggestions.
     *
     * @return list<string>
     */
    public function getBrands(): array
    {
        return Battery::query()
            ->whereNotNull('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand')
            ->values()
            ->all();
    }

    /**
     * Find battery with complete lifecycle history.
     */
    public function findWithHistory(string $id): Battery
    {
        return Battery::query()
            ->with([
                'activeInstallation.vehicle',
                'installations' => fn ($query) => $query->with('vehicle')->latest('installed_at'),
                'inspections.vehicle',
            ])
            ->findOrFail($id);
    }

    /**
     * Replacement / removal events where the battery was taken out or put in.
     *
     * @return Collection<int, BatteryReplacement>
     */
    public function getReplacementHistory(string $batteryId): Collection
    {
        return BatteryReplacement::query()
            ->where(fn ($query) => $query->where('old_battery_id', $batteryId)->orWhere('new_battery_id', $batteryId))
            ->with(['vehicle', 'oldBattery', 'newBattery'])
            ->latest('replaced_at')
            ->latest()
            ->limit(100)
            ->get();
    }

    /**
     * Batteries that can be fitted to a truck: new stock first, then reusable removed (standby) units.
     *
     * @return Collection<int, Battery>
     */
    public function getInStockList(?string $brand = null): Collection
    {
        return Battery::query()
            ->whereIn('status', [BatteryStatus::InStock->value, BatteryStatus::Removed->value])
            ->when($brand, fn ($q) => $q->where('brand', $brand))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [BatteryStatus::InStock->value])
            ->orderBy('serial_number')
            ->get(['id', 'serial_number', 'brand', 'model', 'capacity_ah', 'voltage', 'status', 'purchase_date']);
    }

    public function create(array $data): Battery
    {
        return Battery::query()->create($data);
    }

    public function update(Battery $battery, array $data): Battery
    {
        $battery->update($data);

        return $battery->fresh(['activeInstallation.vehicle']);
    }

    public function findActiveInstallation(string $batteryId): ?BatteryInstallation
    {
        return BatteryInstallation::query()
            ->where('battery_id', $batteryId)
            ->whereNull('removed_at')
            ->first();
    }

    public function findActiveInstallationOnSlot(string $vehicleId, string $slotName): ?BatteryInstallation
    {
        return BatteryInstallation::query()
            ->where('vehicle_id', $vehicleId)
            ->where('slot_name', $slotName)
            ->whereNull('removed_at')
            ->with(['battery'])
            ->first();
    }

    public function createInstallation(array $data): BatteryInstallation
    {
        return BatteryInstallation::query()->create($data);
    }

    public function createReplacement(array $data): BatteryReplacement
    {
        return BatteryReplacement::query()->create($data);
    }

    /**
     * @return array{active_batteries: Collection<int, BatteryInstallation>, history: Collection<int, BatteryInstallation>, replacements: Collection<int, BatteryReplacement>}
     */
    public function getVehicleBatteries(string $vehicleId): array
    {
        $active = BatteryInstallation::query()
            ->where('vehicle_id', $vehicleId)
            ->whereNull('removed_at')
            ->with(['battery', 'vehicle'])
            ->orderBy('slot_name')
            ->get();

        $history = BatteryInstallation::query()
            ->where('vehicle_id', $vehicleId)
            ->whereNotNull('removed_at')
            ->with(['battery', 'vehicle'])
            ->latest('removed_at')
            ->limit(50)
            ->get();

        $replacements = BatteryReplacement::query()
            ->where('vehicle_id', $vehicleId)
            ->with(['vehicle', 'oldBattery', 'newBattery'])
            ->latest('replaced_at')
            ->limit(50)
            ->get();

        return [
            'active_batteries' => $active,
            'history' => $history,
            'replacements' => $replacements,
        ];
    }

    public function delete(Battery $battery): bool
    {
        return (bool) $battery->delete();
    }

    public function createInspection(array $data): BatteryInspection
    {
        return BatteryInspection::query()->create($data);
    }

    private function baseQuery(?string $search, ?string $status, ?string $brand, ?string $vehicleId, ?string $warranty = null): Builder
    {
        return Battery::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($warranty === 'expiring', fn ($query) => $query
                ->warrantyExpiring()
                ->where('status', '!=', BatteryStatus::Scrapped->value))
            ->when($warranty === 'active', fn ($query) => $query->where('warranty_valid_until', '>=', now()->toDateString()))
            ->when($warranty === 'expired', fn ($query) => $query->where('warranty_valid_until', '<', now()->toDateString()))
            ->when($brand, fn ($query) => $query->where('brand', $brand))
            ->when($vehicleId, function ($query) use ($vehicleId): void {
                $query->whereHas('activeInstallation', fn ($installation) => $installation->where('vehicle_id', $vehicleId));
            })
            ->when($search, function ($query) use ($search): void {
                $term = '%'.addcslashes(trim($search), '%_').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('serial_number', 'like', $term)
                        ->orWhere('brand', 'like', $term)
                        ->orWhere('model', 'like', $term)
                        ->orWhere('supplier_name', 'like', $term);
                });
            });
    }
}
