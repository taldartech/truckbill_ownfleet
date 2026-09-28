<?php

namespace App\Http\Requests;

use App\City;
use App\Customer;
use App\Http\Requests\Concerns\NormalizesContactNumber;
use App\Http\Requests\Concerns\ValidatesPartyIdentity;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomerRequest extends FormRequest
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
        return $this->user()?->can('create', Customer::class) ?? false;
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
                Rule::unique('customers', 'name')->where(function ($query) {
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
            'gst_number' => $this->partyGstNumberRules('customers'),
            'pan_number' => $this->partyPanNumberRules('customers'),
            'address' => ['nullable', 'string', 'max:2000'],
            'contact_number' => $this->partyContactNumberRules('customers', required: true),
            'is_broker' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'This name is already used for another customer in the selected city.',
            ...$this->contactNumberMessages(),
            ...$this->partyIdentityMessages(),
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
