<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesVehicleMaintenanceDueFields;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleMaintenanceRequest extends FormRequest
{
    use ValidatesVehicleMaintenanceDueFields;

    public function authorize(): bool
    {
        $maintenance = $this->route('vehicle_maintenance');

        return $maintenance !== null && ($this->user()?->can('update', $maintenance) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['sometimes', 'required', 'uuid', 'exists:vehicles,id'],
            'service_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'odometer_km' => ['sometimes', 'required', 'numeric', 'min:0'],
            'maintenance_service_type_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('maintenance_service_types', 'id')->where(
                    fn ($query) => $query
                        ->where('tenant_id', CurrentTenant::id())
                        ->where('is_active', true),
                ),
            ],
            'service_provider' => ['nullable', 'string', 'max:150'],
            'cost_amount' => ['nullable', 'numeric', 'min:0'],
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
        ];
    }
}
