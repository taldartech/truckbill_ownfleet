<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeleteVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deleted_reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'deleted_reason.required' => 'Please provide a reason for deleting the truck.',
        ];
    }
}