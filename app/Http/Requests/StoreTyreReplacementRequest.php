<?php

namespace App\Http\Requests;

use App\Rules\TyrePositionAllowedOnVehicle;
use App\Tyre;
use Illuminate\Foundation\Http\FormRequest;

class StoreTyreReplacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tyre::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $purchaseDateRules = $this->replacedAtPurchaseDateRules();

        return [
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'position_code' => [
                'required',
                'string',
                'max:64',
                new TyrePositionAllowedOnVehicle($this->input('vehicle_id')),
            ],
            'old_tyre_id' => ['required', 'uuid', 'exists:tyres,id'],
            'new_tyre_id' => ['nullable', 'uuid', 'exists:tyres,id', 'different:old_tyre_id'],
            'replaced_at' => $purchaseDateRules,
            'reason' => ['nullable', 'string', 'max:1000'],
            'stint_km' => ['nullable', 'numeric', 'min:0', 'required_without:odometer_km'],
            'odometer_km' => ['nullable', 'numeric', 'min:0', 'required_without:stint_km'],
        ];
    }

    private function replacedAtPurchaseDateRules(): array
    {
        $rules = ['required', 'date', 'before_or_equal:today'];
        $oldPurchaseDate = $this->purchaseDateForTyre($this->input('old_tyre_id'));
        $newPurchaseDate = $this->purchaseDateForTyre($this->input('new_tyre_id'));

        if ($oldPurchaseDate) {
            $rules[] = "after_or_equal:{$oldPurchaseDate}";
        }

        if ($newPurchaseDate) {
            $rules[] = "after_or_equal:{$newPurchaseDate}";
        }

        return $rules;
    }

    private function purchaseDateForTyre(?string $tyreId): ?string
    {
        if (! $tyreId) {
            return null;
        }

        return Tyre::query()->whereKey($tyreId)->value('purchase_date');
    }
}
