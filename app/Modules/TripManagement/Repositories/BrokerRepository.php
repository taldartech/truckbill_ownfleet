<?php

namespace App\Modules\TripManagement\Repositories;

use App\Broker;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BrokerRepository
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

    public function create(array $data): Broker
    {
        return Broker::query()->create($data);
    }

    public function update(Broker $broker, array $data): Broker
    {
        $broker->update($data);

        return $broker->fresh(['country', 'state', 'city']);
    }

    public function delete(Broker $broker): void
    {
        $broker->delete();
    }

    private function baseQuery(?string $search)
    {
        return Broker::query()
            ->with(['country', 'state', 'city'])
            ->when($search, fn ($query) => $this->applySearch($query, $search));
    }

    private function applySearch($query, string $search): void
    {
        $term = '%'.addcslashes(trim($search), '%_').'%';

        $query->where(function ($builder) use ($term) {
            $builder->where('name', 'like', $term)
                ->orWhere('gst_number', 'like', $term)
                ->orWhere('pan_number', 'like', $term)
                ->orWhere('contact_number', 'like', $term)
                ->orWhere('address', 'like', $term)
                ->orWhereHas('city', fn ($cityQuery) => $cityQuery->where('name', 'like', $term))
                ->orWhereHas('state', fn ($stateQuery) => $stateQuery->where('name', 'like', $term));
        });
    }
}
