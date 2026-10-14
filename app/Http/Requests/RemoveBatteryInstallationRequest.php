<?php

namespace App\Http\Requests;

use App\Battery;
use App\Enums\BatteryRemovalReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RemoveBatteryInstallationRequest extends FormRequest
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
            'removed_at' => ['required', 'date', 'before_or_equal:today'],
            'odometer_km' => ['nullable', 'numeric', 'min:0'],
            'running_km' => ['nullable', 'numeric', 'min:0'],
            'removal_reason' => ['required', Rule::enum(BatteryRemovalReason::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
