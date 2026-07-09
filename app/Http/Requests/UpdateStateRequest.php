<?php

namespace App\Http\Requests;

use App\State;
use App\Support\IndiaCountry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $state = $this->route('state');

        return $state instanceof State
            && $this->user()?->can('update', $state);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var State $state */
        $state = $this->route('state');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                'not_regex:/^\d+$/',
                Rule::unique('states', 'name')
                    ->where('country_id', IndiaCountry::id())
                    ->ignore($state->id),
            ],
            'code' => ['nullable', 'string', 'max:10'],
        ];
    }
}
