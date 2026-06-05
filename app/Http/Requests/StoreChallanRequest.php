<?php

namespace App\Http\Requests;

use App\Challan;
use App\Enums\ChallanPaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChallanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Challan::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'driver_id' => ['required', 'uuid', 'exists:drivers,id'],
            'challan_number' => ['required', 'string', 'max:100'],
            'challan_date' => ['required', 'date'],
            'violation_type' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::enum(ChallanPaymentStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Please select a vehicle.',
            'driver_id.required' => 'Please select a driver.',
            'violation_type.required' => 'Violation type is required.',
        ];
    }
}
