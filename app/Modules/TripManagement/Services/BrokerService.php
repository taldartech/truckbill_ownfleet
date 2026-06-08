<?php

namespace App\Modules\TripManagement\Services;

use App\Broker;
use App\City;
use App\Modules\TripManagement\Repositories\BrokerRepository;
use App\State;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BrokerService
{
    public function __construct(private readonly BrokerRepository $brokerRepository) {}

    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return $this->brokerRepository->paginate($perPage, $search);
    }

    public function listAll(?string $search = null): Collection
    {
        return $this->brokerRepository->listAll($search);
    }

    public function create(array $data, ?string $userId = null): Broker
    {
        $data = $this->normalizeLocationData($data);

        if ($userId !== null) {
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;
        }

        $broker = $this->brokerRepository->create($data);

        return $broker->load(['country', 'state', 'city']);
    }

    public function update(Broker $broker, array $data, ?string $userId = null): Broker
    {
        $data = $this->normalizeLocationData($data);

        if ($userId !== null) {
            $data['updated_by'] = $userId;
        }

        return $this->brokerRepository->update($broker, $data);
    }

    public function delete(Broker $broker): void
    {
        $this->brokerRepository->delete($broker);
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
