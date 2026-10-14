<?php

namespace App\Http\Requests;

use App\Battery;
use App\Enums\BatteryRemovalReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplaceBatteryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('create', Battery::class) ?? false)
            || ($this->user()?->hasPermission('batteries.manage') ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'old_battery_id' => ['required', 'uuid', 'exists:batteries,id'],
            'new_battery_id' => ['nullable', 'uuid', 'exists:batteries,id', 'different:old_battery_id'],
            'slot_name' => ['nullable', 'string', 'max:50'],
            'replaced_at' => ['required', 'date', 'before_or_equal:today'],
            'odometer_km' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', Rule::enum(BatteryRemovalReason::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
