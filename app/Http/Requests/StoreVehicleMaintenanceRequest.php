<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesVehicleMaintenanceDueFields;
use App\Support\CurrentTenant;
use App\VehicleMaintenance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleMaintenanceRequest extends FormRequest
{
    use ValidatesVehicleMaintenanceDueFields;

    protected function prepareForValidation(): void
    {
        if ($this->has('service_provider') && is_string($this->input('service_provider'))) {
            $this->merge([
                'service_provider' => trim($this->input('service_provider')),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', VehicleMaintenance::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'service_date' => ['required', 'date', 'before_or_equal:today'],
            'odometer_km' => ['required', 'numeric', 'min:0'],
            'maintenance_service_type_id' => [
                'required',
                'integer',
                Rule::exists('maintenance_service_types', 'id')->where(
                    fn ($query) => $query
                        ->where('tenant_id', CurrentTenant::id())
                        ->where('is_active', true),
                ),
            ],
            'service_provider' => ['required', 'string', 'max:150'],
            'cost_amount' => ['required', 'numeric', 'gt:0'],
            'next_service_due_km' => ['nullable', 'numeric', 'min:0'],
            'next_service_due_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Please select a truck.',
            'service_date.required' => 'Service date is required.',
            'service_date.before_or_equal' => 'Service date cannot be in the future.',
            'odometer_km.required' => 'Meter reading is required.',
            'maintenance_service_type_id.required' => 'Service type is required.',
            'maintenance_service_type_id.exists' => 'Selected service type is invalid.',
            'service_provider.required' => 'Service provider is required.',
            'cost_amount.required' => 'Cost amount is required.',
            'cost_amount.gt' => 'Cost amount must be greater than 0.',
        ];
    }
}
