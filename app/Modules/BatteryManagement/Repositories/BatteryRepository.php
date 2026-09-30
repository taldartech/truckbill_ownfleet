<?php

namespace App\Modules\BatteryManagement\Repositories;

use App\Battery;
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
        ?string $vehicleId = null
    ): LengthAwarePaginator {
        return $this->baseQuery($search, $status, $brand, $vehicleId)
            ->with(['activeInstallation.vehicle'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get inventory counts in a single fast query for dashboard / tabs.
     *
     * @return array{total: int, in_stock: int, installed: int, removed: int, scrapped: int}
     */
    public function getCounts(): array
    {
        $raw = Battery::query()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_stock,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as installed,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as `removed`,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as scrapped
            ', [
                BatteryStatus::InStock->value,
                BatteryStatus::Installed->value,
                BatteryStatus::Removed->value,
                BatteryStatus::Scrapped->value,
            ])
            ->first();

        return [
            'total' => (int) ($raw?->total ?? 0),
            'in_stock' => (int) ($raw?->in_stock ?? 0),
            'installed' => (int) ($raw?->installed ?? 0),
            'removed' => (int) ($raw?->removed ?? 0),
            'scrapped' => (int) ($raw?->scrapped ?? 0),
        ];
    }

    /**
     * Find battery with complete lifecycle history.
     */
    public function findWithHistory(string $id): Battery
    {
        return Battery::query()
            ->with([
                'activeInstallation.vehicle',
                'installations.vehicle',
                'replacementsAsOld.vehicle',
                'replacementsAsNew.vehicle',
                'inspections.vehicle',
            ])
            ->findOrFail($id);
    }

    /**
     * Get available in-stock batteries for dropdown selection.
     *
     * @return Collection<int, Battery>
     */
    public function getInStockList(?string $brand = null): Collection
    {
        return Battery::query()
            ->where('status', BatteryStatus::InStock->value)
            ->when($brand, fn ($q) => $q->where('brand', $brand))
            ->orderBy('serial_number')
            ->get(['id', 'serial_number', 'brand', 'model', 'capacity_ah', 'voltage', 'status']);
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
            ->with(['battery'])
            ->orderBy('slot_name')
            ->get();

        $history = BatteryInstallation::query()
            ->where('vehicle_id', $vehicleId)
            ->whereNotNull('removed_at')
            ->with(['battery'])
            ->latest('removed_at')
            ->limit(50)
            ->get();

        $replacements = BatteryReplacement::query()
            ->where('vehicle_id', $vehicleId)
            ->with(['oldBattery', 'newBattery'])
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

    private function baseQuery(?string $search, ?string $status, ?string $brand, ?string $vehicleId): Builder
    {
        return Battery::query()
            ->when($status, fn ($query) => $query->where('status', $status))
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
