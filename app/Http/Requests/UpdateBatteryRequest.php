<?php

namespace App\Http\Requests;

use App\Battery;
use App\Enums\BatteryType;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBatteryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $battery = $this->route('battery');

        return $battery instanceof Battery && ($this->user()?->can('update', $battery) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Battery $battery */
        $battery = $this->route('battery');

        return [
            'serial_number' => [
                'sometimes',
                'required',
                'string',
                'max:80',
                Rule::unique('batteries', 'serial_number')
                    ->where(fn ($query) => $query->where('tenant_id', CurrentTenant::id()))
                    ->ignore($battery->id),
            ],
            'brand' => ['sometimes', 'required', 'string', 'max:100'],
            'model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'battery_type' => ['sometimes', 'required', Rule::enum(BatteryType::class)],
            'voltage' => ['sometimes', 'required', 'string', 'max:20'],
            'capacity_ah' => ['sometimes', 'required', 'numeric', 'min:1', 'max:1000'],
            'purchase_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'purchase_cost' => ['sometimes', 'required', 'numeric', 'min:0'],
            'warranty_months' => ['sometimes', 'required', 'integer', 'min:0', 'max:120'],
            'warranty_valid_until' => ['sometimes', 'nullable', 'date'],
            'expected_life_months' => ['sometimes', 'required', 'integer', 'min:1', 'max:240'],
            'supplier_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
