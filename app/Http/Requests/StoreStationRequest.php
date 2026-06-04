<?php

namespace App\Http\Requests;

use App\City;
use App\Station;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Station::class) ?? false;
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
                'max:150',
                Rule::unique('stations', 'name')->where(function ($query) {
                    return $query
                        ->where('tenant_id', CurrentTenant::id())
                        ->where('state_id', $this->input('state_id'))
                        ->where('city_id', $this->input('city_id'));
                }),
            ],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'agency_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $stateId = (int) $this->input('state_id');
            $cityId = (int) $this->input('city_id');

            if ($stateId <= 0 || $cityId <= 0) {
                return;
            }

            $cityBelongsToState = City::query()
                ->whereKey($cityId)
                ->where('state_id', $stateId)
                ->exists();

            if (! $cityBelongsToState) {
                $validator->errors()->add('city_id', 'Selected city does not belong to the selected state.');
            }
        });
    }
}
