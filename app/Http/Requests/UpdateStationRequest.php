<?php

namespace App\Http\Requests;

use App\City;
use App\Station;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $station = $this->route('station');

        return $station instanceof Station
            && ($this->user()?->can('update', $station) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Station $station */
        $station = $this->route('station');

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('stations', 'name')
                    ->where(function ($query) use ($station) {
                        return $query
                            ->where('tenant_id', $station->tenant_id)
                            ->where('state_id', $this->input('state_id'))
                            ->where('city_id', $this->input('city_id'));
                    })
                    ->ignore($station->id),
            ],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
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
