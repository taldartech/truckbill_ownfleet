<?php

namespace App\Http\Requests;

use App\DocumentType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDocumentTypeRequest extends FormRequest
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
        $documentType = $this->route('document_type');

        return $documentType instanceof DocumentType
            && ($this->user()?->can('update', $documentType) ?? false);
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
}
