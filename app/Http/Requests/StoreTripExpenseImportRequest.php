<?php

namespace App\Http\Requests;

use App\Services\TripExpenseImport\StatementColumnMapper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTripExpenseImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->canAccessCompanyPanel()
            && (
                $user->hasPermission('trips.expenses')
                || $user->hasPermission('trips.edit')
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'import_type' => ['required', Rule::in([StatementColumnMapper::TYPE_FASTAG, StatementColumnMapper::TYPE_FUEL])],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.include' => ['sometimes', 'boolean'],
            'rows.*.trip_id' => ['nullable', 'string'],
            'rows.*.transacted_at' => ['required_if:rows.*.include,true,1', 'nullable', 'string'],
            'rows.*.amount' => ['nullable'],
            'rows.*.liters' => ['nullable'],
            'rows.*.rate' => ['nullable'],
            'rows.*.reference' => ['nullable', 'string', 'max:255'],
            'rows.*.remark' => ['nullable', 'string', 'max:1000'],
            'rows.*.plaza' => ['nullable', 'string', 'max:255'],
            'rows.*.pump_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rows.required' => 'No rows were submitted for import.',
            'rows.min' => 'No rows were submitted for import.',
        ];
    }
}
