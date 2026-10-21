<?php

namespace App\Http\Requests;

use App\Services\TripExpenseImport\StatementColumnMapper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyzeTripExpenseImportRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'extensions:csv,txt,xlsx,xls', 'max:5120'],
            'header_row' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please choose a statement file to upload.',
            'file.max' => 'The statement file may not be larger than 5 MB.',
            'import_type.in' => 'Import type must be FASTag or Fuel.',
        ];
    }
}
