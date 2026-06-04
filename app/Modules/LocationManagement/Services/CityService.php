<?php

namespace App\Modules\LocationManagement\Services;

use App\City;
use App\State;
use App\Support\IndiaCountry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CityService
{
    public function paginate(int $perPage = 15, ?int $stateId = null, ?string $search = null): LengthAwarePaginator
    {
        return $this->baseQuery($stateId, $search)
            ->paginate($perPage);
    }

    private function baseQuery(?int $stateId = null, ?string $search = null): Builder
    {
        return City::query()
            ->with('state:id,name,country_id')
            ->whereHas('state', fn ($query) => $query->where('country_id', IndiaCountry::id()))
            ->when($stateId, fn ($query) => $query->where('state_id', $stateId))
            ->when($search, fn ($query) => $query->where('name', 'like', '%'.addcslashes($search, '%_').'%'))
            ->orderBy('name');
    }

    public function create(array $data): City
    {
        $this->ensureIndianState((int) $data['state_id']);

        return City::query()->create([
            'state_id' => $data['state_id'],
            'name' => $data['name'],
        ])->load('state:id,name');
    }

    public function update(City $city, array $data): City
    {
        $this->ensureIndianState((int) $data['state_id']);

        $city->update([
            'state_id' => $data['state_id'],
            'name' => $data['name'],
        ]);

        return $city->fresh()->load('state:id,name');
    }

    private function ensureIndianState(int $stateId): void
    {
        $exists = State::query()
            ->whereKey($stateId)
            ->where('country_id', IndiaCountry::id())
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'state_id' => 'Selected state is invalid.',
            ]);
        }
    }
}
