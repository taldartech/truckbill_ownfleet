<?php

namespace App\Http\Requests;

use App\Support\CurrentTenant;
use App\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
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
            'registration_number' => ['required', 'string', 'max:50'],
            'vehicle_type' => ['required', 'string', 'max:50'],
            'tyre_layout' => [
                'nullable',
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
}
