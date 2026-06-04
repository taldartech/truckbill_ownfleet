<?php

namespace App\Modules\TripManagement\Repositories;

use App\Station;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class StationRepository
{
    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return $this->baseQuery($search)
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function listAll(?string $search = null, int $limit = 500): Collection
    {
        return $this->baseQuery($search)
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function create(array $data): Station
    {
        return Station::query()->create($data);
    }

    public function update(Station $station, array $data): Station
    {
        $station->update($data);

        return $station->fresh(['country', 'state', 'city']);
    }

    public function delete(Station $station): void
    {
        $station->delete();
    }

    private function baseQuery(?string $search)
    {
        return Station::query()
            ->with(['country', 'state', 'city'])
            ->when($search, fn ($query) => $this->applySearch($query, $search));
    }

    private function applySearch($query, string $search): void
    {
        $term = '%'.addcslashes(trim($search), '%_').'%';

        $query->where(function ($builder) use ($term) {
            $builder->where('name', 'like', $term)
                ->orWhereHas('city', fn ($cityQuery) => $cityQuery->where('name', 'like', $term))
                ->orWhereHas('state', fn ($stateQuery) => $stateQuery->where('name', 'like', $term));
        });
    }
}
