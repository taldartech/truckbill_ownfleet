<?php

namespace App\Http\Requests;

use App\Rules\TyrePositionAllowedOnVehicle;
use App\Tyre;
use Illuminate\Foundation\Http\FormRequest;

class StoreTyreRotationRequest extends FormRequest
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
        $vehicleId = $this->resolveRotationVehicleId();

        return [
            'rotation_date' => ['required', 'date', 'before_or_equal:today'],
            'previous_position' => ['required', 'string', 'max:64'],
            'new_position' => [
                'required',
                'string',
                'max:64',
                'different:previous_position',
                new TyrePositionAllowedOnVehicle($vehicleId),
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
            'cost' => ['required', 'numeric', 'min:0'],
            'stint_km' => ['nullable', 'numeric', 'min:0', 'required_without:odometer_km'],
            'odometer_km' => ['nullable', 'numeric', 'min:0', 'required_without:stint_km'],
        ];
    }

    private function resolveRotationVehicleId(): ?string
    {
        $tyre = $this->route('tyre');

        if (! $tyre instanceof Tyre) {
            return null;
        }

        return $tyre->activeInstallation()->value('vehicle_id');
    }
}
