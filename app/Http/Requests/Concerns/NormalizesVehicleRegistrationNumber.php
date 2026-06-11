<?php

namespace App\Http\Requests\Concerns;

use App\Rules\IndianVehicleRegistrationNumber;
use App\Support\VehicleRegistrationNumber;

trait NormalizesVehicleRegistrationNumber
{
    protected function normalizeVehicleRegistrationInput(): void
    {
        if (! $this->has('registration_number')) {
            return;
        }

        $raw = $this->input('registration_number');

        if ($raw === null || $raw === '') {
            return;
        }

        $this->merge([
            'registration_number' => VehicleRegistrationNumber::normalize((string) $raw),
        ]);
    }

    /**
     * @return array<int, IndianVehicleRegistrationNumber|string>
     */
    protected function vehicleRegistrationNumberRules(bool $required = true): array
    {
        $rules = [
            'string',
            'max:12',
            new IndianVehicleRegistrationNumber,
        ];

        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'sometimes', 'required');
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function vehicleRegistrationNumberMessages(): array
    {
        return [
            'registration_number.required' => 'Registration number is required.',
            'registration_number.unique' => 'This registration number is already in use.',
        ];
    }
}
