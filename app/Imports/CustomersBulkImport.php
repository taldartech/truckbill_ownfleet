<?php

namespace App\Imports;

use App\City;
use App\Country;
use App\Customer;
use App\State;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;

class CustomersBulkImport extends BulkImport
{
    private array $uploadedCustomers = [];

    public function __construct(
        private readonly string $tenantId,
        private readonly ?string $userId,
    ) {}

    protected function processRow(
        array $row,
        int $excelRow,
    ): void {
        /*
         * Read Excel values.
         */
        $name = trim((string) ($row['name'] ?? ''));
        $gstNumber = trim((string) ($row['gst_number'] ?? ''));
        $panNumber = trim((string) ($row['pan_number'] ?? ''));
        $contactNumber = trim((string) ($row['contact_number'] ?? ''));
        $address = trim((string) ($row['address'] ?? ''));

        $countryName = trim((string) ($row['country'] ?? ''));
        $stateName = trim((string) ($row['state'] ?? ''));
        $cityName = trim((string) ($row['city'] ?? ''));

        /*
         * Convert is_broker into a boolean.
         *
         * Supported values:
         * 1, 0
         * true, false
         * yes, no
         * y, n
         */
        $isBrokerValue = strtolower(
            trim((string) ($row['is_broker'] ?? ''))
        );

        $isBroker = match ($isBrokerValue) {
            '1',
            'true',
            'yes',
            'y' => true,

            default => false,
        };

        /*
         * Validate basic fields.
         */
        $validator = Validator::make(
            [
                'name' => $name,
                'gst_number' => $gstNumber !== '' ? $gstNumber : null,
                'pan_number' => $panNumber !== '' ? $panNumber : null,
                'contact_number' => $contactNumber !== '' ? $contactNumber : null,
                'address' => $address !== '' ? $address : null,
                'country' => $countryName !== '' ? $countryName : null,
                'state' => $stateName !== '' ? $stateName : null,
                'city' => $cityName !== '' ? $cityName : null,
            ],
            [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'gst_number' => [
                    'nullable',
                    'string',
                    'max:15',
                ],

                'pan_number' => [
                    'nullable',
                    'string',
                    'max:10',
                ],

                'contact_number' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'address' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],

                'country' => [
                    'nullable',
                    'string',
                ],

                'state' => [
                    'required',
                    'string',
                ],

                'city' => [
                    'required',
                    'string',
                ],
            ],
            [
                'name.required' =>
                    'Customer name is required.',

                'name.string' =>
                    'Customer name must be text.',

                'name.max' =>
                    'Customer name may not exceed 255 characters.',

                'gst_number.max' =>
                    'GST number may not exceed 15 characters.',

                'pan_number.max' =>
                    'PAN number may not exceed 10 characters.',

                'contact_number.required' =>
                    'Contact number is required.',

                'contact_number.max' =>
                    'Contact number is invalid.',

                'address.max' =>
                    'Address may not exceed 2000 characters.',

                'state.required' =>
                    'State is required.',

                'city.required' =>
                    'City is required.',
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
         * Normalize names for case-insensitive lookup.
         */
        $normalizedCountryName = mb_strtolower($countryName);
        $normalizedStateName = mb_strtolower($stateName);
        $normalizedCityName = mb_strtolower($cityName);
        $normalizedCustomerName = mb_strtolower($name);

        /*
         * Resolve country.
         *
         * Country is optional.
         */
        $countryId = null;

        if ($countryName !== '') {
            $country = Country::query()
                ->whereRaw(
                    'LOWER(name) = ?',
                    [$normalizedCountryName],
                )
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

            $countryId = $country->id;
        }

        /*
         * Resolve state.
         *
         * If country was supplied, state must belong
         * to that country.
         */
        $stateQuery = State::query()
            ->whereRaw(
                'LOWER(name) = ?',
                [$normalizedStateName],
            );

        if ($countryId !== null) {
            $stateQuery->where('country_id', $countryId);
        }

        $state = $stateQuery->first();

        if (! $state) {
            if ($countryName !== '') {
                $message =
                    "State '{$stateName}' was not found in country '{$countryName}'.";
            } else {
                $message =
                    "State '{$stateName}' was not found.";
            }

            $this->failed++;

            $this->errors[] = [
                'row' => $excelRow,
                'value' => $name,
                'errors' => [
                    $message,
                ],
            ];

            return;
        }

        $stateId = $state->id;

        /*
         * If country was not supplied, derive it from the state.
         *
         * This ensures customers still get country_id
         * whenever the state has a country.
         */
        if ($countryId === null) {
            $countryId = $state->country_id;
        }

        /*
         * Resolve city.
         *
         * City must belong to the selected state.
         */
        $city = City::query()
            ->whereRaw(
                'LOWER(name) = ?',
                [$normalizedCityName],
            )
            ->where('state_id', $stateId)
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

        $cityId = $city->id;

        /*
         * Duplicate inside uploaded file.
         *
         * The actual customer uniqueness depends on:
         *
         * name + state_id + city_id
         */
        $uploadedCustomerKey = implode('|', [
            $normalizedCustomerName,
            $stateId,
            $cityId,
        ]);

        if (isset($this->uploadedCustomers[$uploadedCustomerKey])) {
            $this->failed++;

            $this->errors[] = [
                'row' => $excelRow,
                'value' => $name,
                'errors' => [
                    'This customer is duplicated in the uploaded file.',
                ],
            ];

            return;
        }

        /*
         * Check if customer already exists.
         *
         * Same uniqueness rule as StoreCustomerRequest:
         *
         * tenant_id + name + state_id + city_id
         */
        $exists = Customer::query()
            ->where('tenant_id', $this->tenantId)
            ->whereRaw(
                'LOWER(name) = ?',
                [$normalizedCustomerName],
            )
            ->where('state_id', $stateId)
            ->where('city_id', $cityId)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            $this->failed++;

            $this->errors[] = [
                'row' => $excelRow,
                'value' => $name,
                'errors' => [
                    'This customer already exists in the selected city.',
                ],
            ];

            return;
        }

        /*
         * Remember this customer so duplicate rows
         * later in the same Excel file are rejected.
         */
        $this->uploadedCustomers[$uploadedCustomerKey] = true;

        /*
         * Create customer.
         */
        try {
            Customer::query()->create([
                'tenant_id' => $this->tenantId,

                'country_id' => $countryId,
                'state_id' => $stateId,
                'city_id' => $cityId,

                'name' => $name,
                'gst_number' => $gstNumber !== '' ? $gstNumber : null,
                'pan_number' => $panNumber !== '' ? $panNumber : null,
                'address' => $address !== '' ? $address : null,
                'contact_number' => $contactNumber,
                'is_broker' => $isBroker,

                'created_by' => $this->userId,
                'updated_by' => $this->userId,
            ]);

            $this->successful++;
        } catch (QueryException $exception) {
            /*
             * Additional protection against a race condition
             * between duplicate checking and insertion.
             */
            if ($this->isDuplicateException($exception)) {
                $this->failed++;

                $this->errors[] = [
                    'row' => $excelRow,
                    'value' => $name,
                    'errors' => [
                        'This customer already exists in the selected city.',
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