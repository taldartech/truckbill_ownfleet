<?php

namespace Database\Seeders;

use App\City;
use App\Country;
use App\State;
use Illuminate\Database\Seeder;

class IndiaLocationSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{country: array{name: string, code: string}, states: array<int, array{name: string, code: string, cities: array<int, string>}>} $data */
        $data = require database_path('data/india_locations.php');

        $country = Country::query()->updateOrCreate(
            ['code' => $data['country']['code']],
            ['name' => $data['country']['name']]
        );

        foreach ($data['states'] as $stateData) {
            $state = State::query()->updateOrCreate(
                [
                    'country_id' => $country->id,
                    'name' => $stateData['name'],
                ],
                ['code' => $stateData['code']]
            );

            foreach ($stateData['cities'] as $cityName) {
                City::query()->updateOrCreate(
                    [
                        'state_id' => $state->id,
                        'name' => $cityName,
                    ]
                );
            }
        }
    }
}
