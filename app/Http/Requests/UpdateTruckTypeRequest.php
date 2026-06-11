<?php

namespace App\Http\Requests;

use App\TruckType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTruckTypeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('name') && $this->input('name') !== null) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }
    }

    public function authorize(): bool
    {
        /** @var TruckType|null $truckType */
        $truckType = $this->route('truck_type');

        if ($truckType === null) {
            return false;
        }

        return $this->user()?->can('update', $truckType) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Truck type name is required.',
        ];
    }
}
