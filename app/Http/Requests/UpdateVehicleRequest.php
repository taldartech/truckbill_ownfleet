<?php

namespace App\Http\Requests;

use App\Vehicle;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Vehicle|null $vehicle */
        $vehicle = $this->route('vehicle');

        if ($vehicle === null) {
            return false;
        }

        return $this->user()?->can('update', $vehicle) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'registration_number' => ['sometimes', 'string', 'max:50'],
            'vehicle_type' => ['sometimes', 'string', 'max:50'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'manufacture_year' => ['nullable', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
            'odometer_km' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
