<?php

namespace App\Http\Requests;


use App\TripExpenseTypeMaster;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTripExpenseTypeRequest extends FormRequest
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
                'code' => strtoupper(\Illuminate\Support\Str::slug((string) $this->input('name'), '_')),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', TripExpenseTypeMaster::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::notIn(['Diesel', 'Other', 'DIESEL', 'OTHER', 'diesel', 'other']),
                Rule::unique('trip_expense_types', 'name')->whereNull('deleted_at'),
            ],
            'code' => [
                'required',
                'string',
                'max:64',
                Rule::notIn(TripExpenseTypeMaster::SYSTEM_CODES),
                Rule::unique('trip_expense_types', 'code')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Trip expense type name is required.',
            'name.not_in' => 'Diesel and Other are default system expense types.',
            'name.unique' => 'A trip expense type with this name already exists.',
            'code.not_in' => 'Diesel and Other are default system expense types.',
            'code.unique' => 'A trip expense type with this code already exists.',
        ];
    }
}
