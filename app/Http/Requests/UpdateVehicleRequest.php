<?php

namespace App\Http\Requests;

use App\Support\CurrentTenant;
use App\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('registration_number') && $this->input('registration_number') !== null) {
            $this->merge([
                'registration_number' => strtoupper(trim((string) $this->input('registration_number'))),
            ]);
        }
    }

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
        /** @var Vehicle $vehicle */
        $vehicle = $this->route('vehicle');

        return [
            'registration_number' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('vehicles', 'registration_number')
                    ->where(fn ($query) => $query->where('tenant_id', CurrentTenant::id()))
                    ->ignore($vehicle->id),
            ],
            'vehicle_type' => ['sometimes', 'string', 'max:50'],
            'tyre_layout' => [
                'sometimes',
                'string',
                'max:64',
                Rule::exists('tyre_layouts', 'code')->where(function ($query) {
                    $query->where('tenant_id', CurrentTenant::id())->where('is_active', true);
                }),
            ],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'manufacture_year' => ['nullable', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
            'odometer_km' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'registration_number.unique' => 'This registration number is already in use.',
            'tyre_layout.exists' => 'Selected tyre layout is invalid or inactive.',
        ];
    }
}
