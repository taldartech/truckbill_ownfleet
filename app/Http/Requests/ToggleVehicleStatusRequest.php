<?php

namespace App\Http\Requests;

use App\Vehicle;
use Illuminate\Foundation\Http\FormRequest;

class ToggleVehicleStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Vehicle|null $vehicle */
        $vehicle = $this->route('vehicle');

        if ($vehicle === null) {
            return false;
        }

        return $this->user()?->can('toggleStatus', $vehicle) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }
}
