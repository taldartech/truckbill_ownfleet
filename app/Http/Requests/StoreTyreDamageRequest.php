<?php

namespace App\Http\Requests;

use App\Enums\TyreDamageType;
use App\Rules\TyrePositionAllowedOnVehicle;
use App\Tyre;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTyreDamageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tyre = $this->route('tyre');

        return $tyre instanceof Tyre && ($this->user()?->can('update', $tyre) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['nullable', 'uuid', 'exists:vehicles,id'],
            'position_code' => [
                'nullable',
                'string',
                'max:64',
                new TyrePositionAllowedOnVehicle($this->input('vehicle_id')),
            ],
            'damage_type' => ['required', Rule::enum(TyreDamageType::class)],
            'km_at_occurrence' => ['nullable', 'numeric', 'min:0'],
            'occurred_at' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'repair_cost' => ['nullable', 'numeric', 'min:0'],
            'is_repaired' => ['nullable', 'boolean'],
        ];
    }
}
