<?php

namespace App\Rules;

use App\Support\VehicleRegistrationNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IndianVehicleRegistrationNumber implements ValidationRule
{
    /**
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! VehicleRegistrationNumber::isValid(is_string($value) ? $value : null)) {
            $fail('Please enter a valid vehicle registration number (e.g. RJ27AV5055).');
        }
    }
}
