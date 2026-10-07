<?php

namespace App\Http\Requests;

use App\TripExpenseTypeMaster;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTripExpenseTypeRequest extends FormRequest
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
        $tripExpenseType = $this->route('trip_expense_type');

        return $tripExpenseType instanceof TripExpenseTypeMaster
            && ($this->user()?->can('update', $tripExpenseType) ?? false);
    }

    public function rules(): array
    {
        $tripExpenseType = $this->route('trip_expense_type');
        $id = $tripExpenseType instanceof TripExpenseTypeMaster ? $tripExpenseType->id : $tripExpenseType;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('trip_expense_types', 'name')
                    ->ignore($id),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Trip expense type name is required.',
            'name.unique' => 'A trip expense type with this name already exists.',
        ];
    }
}
