<?php

namespace App\Http\Requests;

use App\Emi;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Emi::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'lender_name' => ['required', 'string'],
            'emi_amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'status' => ['nullable', 'string'],
        ];
    }
}
