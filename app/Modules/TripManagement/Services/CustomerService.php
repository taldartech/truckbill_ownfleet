<?php

namespace App\Modules\TripManagement\Services;

use App\City;
use App\Customer;
use App\Modules\TripManagement\Repositories\CustomerRepository;
use App\State;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CustomerService
{
    public function __construct(private readonly CustomerRepository $customerRepository) {}

    public function paginate(int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return $this->customerRepository->paginate($perPage, $search);
    }

    public function listAll(?string $search = null): Collection
    {
        return $this->customerRepository->listAll($search);
    }

    public function create(array $data, ?string $userId = null): Customer
    {
        $data = $this->normalizeLocationData($data);

        if ($userId !== null) {
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;
        }

        $customer = $this->customerRepository->create($data);

        return $customer->load(['country', 'state', 'city']);
    }

    public function update(Customer $customer, array $data, ?string $userId = null): Customer
    {
        $data = $this->normalizeLocationData($data);

        if ($userId !== null) {
            $data['updated_by'] = $userId;
        }

        return $this->customerRepository->update($customer, $data);
    }

    public function delete(Customer $customer): void
    {
        $this->customerRepository->delete($customer);
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
