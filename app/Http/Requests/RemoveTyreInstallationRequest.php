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
            'removed_at' => ['nullable', 'date'],
            'stint_km' => ['required', 'numeric', 'min:0'],
        ];
    }
}
