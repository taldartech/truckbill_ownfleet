<?php

namespace App\Http\Requests;

use App\Battery;
use App\Enums\BatteryCondition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatteryInspectionRequest extends FormRequest
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
        return [
            'inspected_at' => ['required', 'date', 'before_or_equal:today'],
            'voltage_reading' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'specific_gravity' => ['nullable', 'numeric', 'min:1', 'max:1.4'],
            'condition' => ['required', Rule::enum(BatteryCondition::class)],
            'action_taken' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'specific_gravity.min' => 'Specific gravity is usually between 1.100 and 1.300.',
            'specific_gravity.max' => 'Specific gravity is usually between 1.100 and 1.300.',
        ];
    }
}
