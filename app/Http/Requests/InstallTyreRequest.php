<?php

namespace App\Http\Requests;

use App\Rules\TyrePositionAllowedOnVehicle;
use App\Tyre;
use Illuminate\Foundation\Http\FormRequest;

class InstallTyreRequest extends FormRequest
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
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'position_code' => [
                'required',
                'string',
                'max:64',
                new TyrePositionAllowedOnVehicle($this->input('vehicle_id')),
            ],
            'installed_at' => ['required', 'date', 'before_or_equal:today'],
            'km_at_installation' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
