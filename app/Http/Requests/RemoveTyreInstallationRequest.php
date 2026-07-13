<?php

namespace App\Http\Requests;

use App\TyreInstallation;
use Illuminate\Foundation\Http\FormRequest;

class RemoveTyreInstallationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $installation = $this->route('tyre_installation');

        return $installation instanceof TyreInstallation
            && ($this->user()?->can('update', $installation->tyre) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $purchaseDate = $this->resolveTyrePurchaseDate();

        return [
            'removed_at' => array_merge(
                ['nullable', 'date', 'before_or_equal:today'],
                $purchaseDate ? ["after_or_equal:{$purchaseDate}"] : [],
            ),
            'stint_km' => ['nullable', 'numeric', 'min:0', 'required_without:odometer_km'],
            'odometer_km' => ['nullable', 'numeric', 'min:0', 'required_without:stint_km'],
        ];
    }

    private function resolveTyrePurchaseDate(): ?string
    {
        $installation = $this->route('tyre_installation');

        return $installation instanceof \App\TyreInstallation
            && $installation->tyre?->purchase_date
                ? $installation->tyre->purchase_date->toDateString()
                : null;
    }
}
