<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TripBiltyPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'show_freight' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('show_freight')) {
            $this->merge([
                'show_freight' => filter_var($this->input('show_freight'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    ?? $this->input('show_freight'),
            ]);
        }
    }

    public function showFreight(): bool
    {
        return (bool) $this->boolean('show_freight');
    }
}
