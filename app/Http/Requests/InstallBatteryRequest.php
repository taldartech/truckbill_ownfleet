<?php

namespace App\Http\Requests;

use App\Battery;
use Illuminate\Foundation\Http\FormRequest;

class InstallBatteryRequest extends FormRequest
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
        $purchaseDate = $this->resolveBatteryPurchaseDate();

        return [
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'slot_name' => ['nullable', 'string', 'max:50'],
            'installed_at' => array_merge(
                ['required', 'date', 'before_or_equal:today'],
                $purchaseDate ? ["after_or_equal:{$purchaseDate}"] : [],
            ),
            'km_at_installation' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function resolveBatteryPurchaseDate(): ?string
    {
        $battery = $this->route('battery');

        return $battery instanceof Battery && $battery->purchase_date
            ? $battery->purchase_date->toDateString()
            : null;
    }
}
