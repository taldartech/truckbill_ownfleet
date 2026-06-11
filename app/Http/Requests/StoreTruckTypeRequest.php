<?php

namespace App\Http\Requests;

use App\Support\CurrentTenant;
use App\Support\TruckTypeDefaults;
use App\TruckType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTruckTypeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('name') && $this->input('name') !== null) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }

        if (! $this->filled('code') && $this->filled('name')) {
            $this->merge([
                'code' => TruckTypeDefaults::generateCode(
                    (string) $this->input('name'),
                    CurrentTenant::requireId(),
                ),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', TruckType::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::unique('truck_types', 'code')->where(
                    fn ($query) => $query->where('tenant_id', CurrentTenant::id()),
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Truck type name is required.',
            'code.unique' => 'This truck type already exists.',
        ];
    }
}
