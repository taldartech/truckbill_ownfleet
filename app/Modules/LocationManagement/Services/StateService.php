<?php

namespace App\Modules\LocationManagement\Services;

use App\State;
use App\Support\IndiaCountry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class StateService
{
    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return $this->baseQuery($search)
            ->paginate($perPage);
    }

    private function baseQuery(?string $search = null): Builder
    {
        return State::query()
            ->where('country_id', IndiaCountry::id())
            ->withCount('cities')
            ->when($search, fn ($query) => $query->where(function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_').'%';
                $query->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term);
            }))
            ->orderBy('name');
    }

    public function create(array $data): State
    {
        return State::query()->create([
            'country_id' => IndiaCountry::id(),
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
        ]);
    }

    public function update(State $state, array $data): State
    {
        $state->update([
            'name' => $data['name'],
            'code' => $data['code'] ?? null,
        ]);

        return $state->fresh()->loadCount('cities');
    }
}
