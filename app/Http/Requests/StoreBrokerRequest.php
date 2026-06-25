<?php

namespace App\Http\Requests;

use App\Broker;
use App\City;
use App\Http\Requests\Concerns\NormalizesContactNumber;
use App\Http\Requests\Concerns\ValidatesPartyIdentity;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBrokerRequest extends FormRequest
{
    use NormalizesContactNumber;
    use ValidatesPartyIdentity;

    protected function prepareForValidation(): void
    {
        $this->normalizeContactNumberInput();
        $this->normalizePartyIdentityFields();
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', Broker::class) ?? false;
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
                'max:255',
                Rule::unique('brokers', 'name')->where(function ($query) {
                    return $query
                        ->where('tenant_id', CurrentTenant::id())
                        ->where('state_id', $this->input('state_id'))
                        ->where('city_id', $this->input('city_id'))
                        ->whereNull('deleted_at');
                }),
            ],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'agency_id' => ['nullable', 'integer', 'min:1'],
            'gst_number' => $this->partyGstNumberRules('brokers'),
            'pan_number' => $this->partyPanNumberRules('brokers'),
            'address' => ['nullable', 'string', 'max:2000'],
            'contact_number' => $this->partyContactNumberRules('brokers', required: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This name is already used for another reference in the selected city.',
            ...$this->contactNumberMessages(),
            ...$this->partyIdentityMessages('reference'),
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
