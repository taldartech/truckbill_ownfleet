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
            'replaced_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'stint_km' => ['nullable', 'numeric', 'min:0', 'required_without:odometer_km'],
            'odometer_km' => ['nullable', 'numeric', 'min:0', 'required_without:stint_km'],
        ];
    }
}
