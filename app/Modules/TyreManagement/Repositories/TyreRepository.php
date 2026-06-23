<?php

namespace App\Modules\TyreManagement\Repositories;

use App\Tyre;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TyreRepository
{
    public function paginate(int $perPage = 15, ?string $search = null, ?string $status = null, ?string $vehicleId = null): LengthAwarePaginator
    {
        return $this->baseQuery($search, $status, $vehicleId)
            ->with(['activeInstallation.vehicle'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function findWithHistory(string $id): Tyre
    {
        return Tyre::query()
            ->with([
                'activeInstallation.vehicle',
                'installations.vehicle',
                'rotations.vehicle',
                'damages.vehicle',
                'expenses',
            ])
            ->findOrFail($id);
    }

    public function create(array $data): Tyre
    {
        return Tyre::query()->create($data);
    }

    public function update(Tyre $tyre, array $data): Tyre
    {
        $tyre->update($data);

        return $tyre->fresh();
    }

    private function baseQuery(?string $search, ?string $status, ?string $vehicleId = null): Builder
    {
        return Tyre::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($vehicleId, function ($query) use ($vehicleId): void {
                $query->whereHas('activeInstallation', fn ($installation) => $installation->where('vehicle_id', $vehicleId));
            })
            ->when($search, function ($query) use ($search): void {
                $term = '%'.addcslashes(trim($search), '%_').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('serial_number', 'like', $term)
                        ->orWhere('brand', 'like', $term)
                        ->orWhere('size', 'like', $term);
                });
            });
    }
}
