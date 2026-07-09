<?php

namespace App\Http\Requests;

use App\State;
use App\Support\IndiaCountry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', State::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:120',
                'not_regex:/^\d+$/',
                Rule::unique('states', 'name')->where('country_id', IndiaCountry::id()),
            ],
            'code' => ['nullable', 'string', 'max:10'],
        ];
    }
}
