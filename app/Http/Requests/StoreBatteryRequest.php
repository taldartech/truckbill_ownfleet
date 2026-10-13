<?php

namespace App\Http\Requests;

use App\Battery;
use App\Enums\BatteryType;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatteryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Battery::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'serial_number' => [
                'required',
                'string',
                'max:80',
                Rule::unique('batteries', 'serial_number')
                    ->where(fn ($query) => $query->where('tenant_id', CurrentTenant::id())),
            ],
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'battery_type' => ['nullable', Rule::enum(BatteryType::class)],
            'voltage' => ['nullable', 'string', 'max:20'],
            'capacity_ah' => ['required', 'numeric', 'min:1', 'max:1000'],
            'purchase_date' => ['required', 'date', 'before_or_equal:today'],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'warranty_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'warranty_valid_until' => ['nullable', 'date', 'after_or_equal:purchase_date'],
            'expected_life_months' => ['nullable', 'integer', 'min:1', 'max:240'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['prohibited'],
        ];
    }
}
