<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'driver_id' => ['required', 'uuid', 'exists:drivers,id'],
            'trip_number' => ['nullable', 'string', 'max:100'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date'],
            'route_legs' => ['nullable', 'array'],
            'route_legs.*.from_location' => ['required_with:route_legs', 'string'],
            'route_legs.*.to_location' => ['required_with:route_legs', 'string'],
            'route_legs.*.route_type' => ['required_with:route_legs', 'string'],
            'route_legs.*.distance_km' => ['required_with:route_legs', 'numeric', 'min:0'],
            'route_legs.*.sequence_no' => ['required_with:route_legs', 'integer', 'min:1'],
        ];
    }
}
