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
        return [
            'removed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'stint_km' => ['nullable', 'numeric', 'min:0', 'required_without:odometer_km'],
            'odometer_km' => ['nullable', 'numeric', 'min:0', 'required_without:stint_km'],
        ];
    }
}
