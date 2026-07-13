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
        $purchaseDate = $this->resolveTyrePurchaseDate();

        return [
            'vehicle_id' => ['nullable', 'uuid', 'exists:vehicles,id'],
            'position_code' => [
                'nullable',
                'string',
                'max:64',
                new TyrePositionAllowedOnVehicle($this->input('vehicle_id')),
            ],
            'damage_type' => ['required', Rule::enum(TyreDamageType::class)],
            'odometer_km' => ['required', 'numeric', 'min:0'],
            'occurred_at' => array_merge(
                ['required', 'date', 'before_or_equal:today'],
                $purchaseDate ? ["after_or_equal:{$purchaseDate}"] : [],
            ),
            'description' => ['nullable', 'string', 'max:2000'],
            'repair_cost' => ['nullable', 'numeric', 'min:0'],
            'is_repaired' => ['nullable', 'boolean'],
            'expected_life_km' => [
                Rule::requiredIf(fn () => $this->input('damage_type') === TyreDamageType::Recapped->value),
                'nullable',
                'numeric',
                'min:1000',
            ],
        ];
    }

    private function resolveTyrePurchaseDate(): ?string
    {
        $tyre = $this->route('tyre');

        return $tyre instanceof Tyre && $tyre->purchase_date
            ? $tyre->purchase_date->toDateString()
            : null;
    }
}
