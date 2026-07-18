<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesVehicleRegistrationNumber;
use App\Support\CurrentTenant;
use App\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    use NormalizesVehicleRegistrationNumber;

    protected function prepareForValidation(): void
    {
        $this->normalizeVehicleRegistrationInput();
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', Vehicle::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'registration_number' => [
                ...$this->vehicleRegistrationNumberRules(),
                Rule::unique('vehicles', 'registration_number')->where(
                    fn ($query) => $query->where('tenant_id', CurrentTenant::id())->whereNull('deleted_at'),
                ),
            ],
            'vehicle_type' => [
                'required',
                'string',
                'max:64',
                Rule::exists('truck_types', 'code')->where(
                    fn ($query) => $query->where('tenant_id', CurrentTenant::id())->where('is_active', true),
                ),
            ],
            'tyre_layout' => [
                'required',
                'string',
                'max:64',
                Rule::exists('tyre_layouts', 'code')->where(function ($query) {
                    $query->where('tenant_id', CurrentTenant::id())->where('is_active', true);
                }),
            ],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'manufacture_year' => ['nullable', 'integer', 'min:1980', 'max:'.date('Y')],
            'odometer_km' => ['nullable', 'numeric', 'min:0'],
            'default_mileage_km_per_liter' => ['nullable', 'numeric', 'min:0'],
            'max_load_ton' => ['nullable', 'numeric', 'min:0'],
            'fuel_tank_capacity_ltr' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->vehicleRegistrationNumberMessages(),
            'vehicle_type.exists' => 'Selected truck type is invalid.',
            'tyre_layout.exists' => 'Selected tyre layout is invalid or inactive.',
        ];
    }
}
