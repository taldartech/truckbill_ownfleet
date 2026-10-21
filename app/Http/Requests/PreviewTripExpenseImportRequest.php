<?php

namespace App\Http\Requests;

use App\Services\TripExpenseImport\StatementColumnMapper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewTripExpenseImportRequest extends FormRequest
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

    protected function prepareForValidation(): void
    {
        $map = $this->input('column_map');

        if (is_string($map)) {
            $decoded = json_decode($map, true);
            if (is_array($decoded)) {
                $this->merge(['column_map' => $decoded]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'import_type' => ['required', Rule::in([StatementColumnMapper::TYPE_FASTAG, StatementColumnMapper::TYPE_FUEL])],
            'statement_date' => ['required', 'date_format:Y-m-d'],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'extensions:csv,txt,xlsx,xls', 'max:5120'],
            'header_row' => ['nullable', 'integer', 'min:1', 'max:100'],
            'column_map' => ['required', 'array'],
            'column_map.*' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'statement_date.required' => 'Statement date is required.',
            'file.required' => 'Please choose a statement file to upload.',
            'column_map.required' => 'Column mapping is required.',
        ];
    }
}
