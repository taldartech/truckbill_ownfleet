<?php

namespace App\Imports;

use App\City;
use App\Country;
use App\State;
use App\Station;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class StationsBulkImport extends BulkImport
{
    private array $uploadedStations = [];

    public function __construct(
        private readonly string $tenantId,
        private readonly ?string $userId,
    ) {}

    protected function processRow(
        array $row,
        int $excelRow,
    ): void {
        $name = trim((string) ($row['name'] ?? ''));
        $countryName = trim((string) ($row['country'] ?? ''));
        $stateName = trim((string) ($row['state'] ?? ''));
        $cityName = trim((string) ($row['city'] ?? ''));

        /*
         * Validate basic Excel values.
         */
        $validator = Validator::make(
            [
                'name' => $name,
                'country' => $countryName,
                'state' => $stateName,
                'city' => $cityName,
            ],
            [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'country' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'state' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'city' => [
                    'required',
                    'string',
                    'max:255',
                ],
            ],
            [
                'name.required' =>
                    'Station name is required.',

                'name.string' =>
                    'Station name must be text.',

                'name.max' =>
                    'Station name may not exceed 255 characters.',

                'country.required' =>
                    'Country is required.',

                'country.string' =>
                    'Country must be text.',

                'state.required' =>
                    'State is required.',

                'state.string' =>
                    'State must be text.',

                'city.required' =>
                    'City is required.',

                'city.string' =>
                    'City must be text.',
            ],
        );

        if ($validator->fails()) {
            $this->failed++;

            $this->errors[] = [
                'row' => $excelRow,
                'value' => $name,
                'errors' => $validator->errors()->all(),
            ];

            return;
        }

        /*
         * Resolve country.
         */
        $country = Country::query()
            ->whereRaw('LOWER(name) = ?', [
                mb_strtolower($countryName),
            ])
            ->first();

        if (! $country) {
            $this->failed++;

            $this->errors[] = [
                'row' => $excelRow,
                'value' => $name,
                'errors' => [
                    "Country '{$countryName}' was not found.",
                ],
            ];

            return;
        }

        /*
         * Resolve state belonging to the selected country.
         */
        $state = State::query()
            ->where('country_id', $country->id)
            ->whereRaw('LOWER(name) = ?', [
                mb_strtolower($stateName),
            ])
            ->first();

        if (! $state) {
            $this->failed++;

            $this->errors[] = [
                'row' => $excelRow,
                'value' => $name,
                'errors' => [
                    "State '{$stateName}' was not found in country '{$countryName}'.",
                ],
            ];

            return;
        }

        /*
         * Resolve city belonging to the selected state.
         */
        $city = City::query()
            ->where('state_id', $state->id)
            ->whereRaw('LOWER(name) = ?', [
                mb_strtolower($cityName),
            ])
            ->first();

        if (! $city) {
            $this->failed++;

            $this->errors[] = [
                'row' => $excelRow,
                'value' => $name,
                'errors' => [
                    "City '{$cityName}' was not found in state '{$stateName}'.",
                ],
            ];

            return;
        }

        /*
         * Normalize station name for duplicate comparison.
         */
        $normalizedName = mb_strtolower($name);

        /*
         * Duplicate inside uploaded file.
         *
         * A station belongs to the current branch, so include
         * the resolved city/state in the duplicate key.
         */
        $duplicateKey = implode('|', [
            $normalizedName,
            $state->id,
            $city->id,
        ]);

        if (isset($this->uploadedStations[$duplicateKey])) {
            $this->failed++;

            $this->errors[] = [
                'row' => $excelRow,
                'value' => $name,
                'errors' => [
                    'This station is duplicated in the uploaded file.',
                ],
            ];

            return;
        }

        /*
         * Check whether the station already exists.
         *
         * BranchScoped automatically limits this query to
         * the current branch.
         */
        $exists = Station::query()
            ->whereRaw('LOWER(name) = ?', [
                $normalizedName,
            ])
            ->where('state_id', $state->id)
            ->where('city_id', $city->id)
            ->exists();

        if ($exists) {
            $this->failed++;

            $this->errors[] = [
                'row' => $excelRow,
                'value' => $name,
                'errors' => [
                    'This station already exists in the selected city.',
                ],
            ];

            return;
        }

        /*
         * Remember this station so duplicate rows later
         * in the same file are rejected.
         */
        $this->uploadedStations[$duplicateKey] = true;

        /*
         * Create station.
         *
         * branch_id is intentionally NOT supplied.
         * BranchScoped will populate it automatically.
         */
        try {
            Station::query()->create([
                'tenant_id' => $this->tenantId,
                'country_id' => $country->id,
                'state_id' => $state->id,
                'city_id' => $city->id,
                'name' => $name,
            ]);

            $this->successful++;
        } catch (QueryException $exception) {
            if ($this->isDuplicateException($exception)) {
                $this->failed++;

                $this->errors[] = [
                    'row' => $excelRow,
                    'value' => $name,
                    'errors' => [
                        'This station already exists in the selected city.',
                    ],
                ];

                return;
            }

            throw $exception;
        }
    }

    private function isDuplicateException(
        QueryException $exception,
    ): bool {
        return (int) $exception->getCode() === 23000
            && str_contains(
                strtolower($exception->getMessage()),
                'duplicate',
            );
    }
}