<?php

namespace App\Rules;

use App\Support\TyrePositionCatalog;
use App\Vehicle;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TyrePositionAllowedOnVehicle implements ValidationRule
{
    public function __construct(
        private readonly ?string $vehicleId,
        private readonly TyrePositionCatalog $catalog = new TyrePositionCatalog,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Select a valid tyre position.');

            return;
        }

        if ($this->vehicleId === null || $this->vehicleId === '') {
            $fail('Select a vehicle first.');

            return;
        }

        $vehicle = Vehicle::query()->find($this->vehicleId);
        if ($vehicle === null) {
            $fail('The selected vehicle was not found.');

            return;
        }

        if (! $this->catalog->isAllowedOnVehicle($vehicle, $value)) {
            $fail('This position is not valid for the selected truck layout.');
        }
    }
}
