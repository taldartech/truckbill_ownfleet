<?php

namespace App\Http\Requests;

use App\Broker;
use App\City;
use App\Http\Requests\Concerns\NormalizesContactNumber;
use App\Http\Requests\Concerns\ValidatesPartyIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBrokerRequest extends FormRequest
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
        $broker = $this->route('broker');

        return $broker instanceof Broker
            && ($this->user()?->can('update', $broker) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Broker $broker */
        $broker = $this->route('broker');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('brokers', 'name')
                    ->where(function ($query) use ($broker) {
                        return $query
                            ->where('tenant_id', $broker->tenant_id)
                            ->where('state_id', $this->input('state_id'))
                            ->where('city_id', $this->input('city_id'))
                            ->whereNull('deleted_at');
                    })
                    ->ignore($broker->id),
            ],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'gst_number' => $this->partyGstNumberRules('brokers', $broker->id, $broker->tenant_id),
            'pan_number' => $this->partyPanNumberRules('brokers', $broker->id, $broker->tenant_id),
            'address' => ['nullable', 'string', 'max:2000'],
            'contact_number' => $this->partyContactNumberRules('brokers', $broker->id, $broker->tenant_id, required: true),
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
