<?php

namespace App\Modules\TripManagement\Services;

use App\City;
use App\Modules\TripManagement\Repositories\StationRepository;
use App\State;
use App\Station;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class StationService
{
    public function __construct(private readonly StationRepository $stationRepository) {}

    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return $this->stationRepository->paginate($perPage, $search);
    }

    public function listAll(?string $search = null): Collection
    {
        return $this->stationRepository->listAll($search);
    }

    public function create(array $data): Station
    {
        $data = $this->normalizeLocationData($data);

        $station = $this->stationRepository->create($data);

        return $station->load(['country', 'state', 'city']);
    }

    public function update(Station $station, array $data): Station
    {
        $data = $this->normalizeLocationData($data);

        return $this->stationRepository->update($station, $data);
    }

    public function delete(Station $station): void
    {
        $this->stationRepository->delete($station);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeLocationData(array $data): array
    {
        if (! empty($data['city_id'])) {
            $city = City::query()->with('state')->find($data['city_id']);

            if ($city) {
                $data['state_id'] = $city->state_id;
                $data['country_id'] = $city->state?->country_id;
            }
        } elseif (! empty($data['state_id'])) {
            $state = State::query()->find($data['state_id']);

            if ($state) {
                $data['country_id'] = $state->country_id;
            }
        }

        return $data;
    }
}
