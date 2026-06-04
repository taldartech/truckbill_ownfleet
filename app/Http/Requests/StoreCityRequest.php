<?php

namespace App\Http\Requests;

use App\City;
use App\Support\IndiaCountry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', City::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'state_id' => [
                'required',
                'integer',
                Rule::exists('states', 'id')->where('country_id', IndiaCountry::id()),
            ],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('cities', 'name')->where('state_id', $this->input('state_id')),
            ],
        ];
    }
}
