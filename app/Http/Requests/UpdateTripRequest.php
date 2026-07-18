<?php

namespace App\Http\Requests;

use App\Enums\TripExpenseType;
use App\Enums\TripStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        $trip = $this->route('trip');

        return $trip !== null && ($this->user()?->can('update', $trip) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['prohibited'],
            'driver_id' => ['prohibited'],
            'substitute_driver_id' => ['nullable', 'uuid', 'exists:drivers,id'],
            'trip_number' => ['prohibited'],
            'trip_type' => ['prohibited'],
            'start_meter_reading_km' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'total_distance_km' => ['prohibited'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'broker_id' => ['nullable', 'integer', 'exists:brokers,id'],
            'start_station_id' => ['sometimes', 'required', 'integer', 'exists:stations,id'],
            'status' => ['sometimes', Rule::enum(TripStatus::class)],
            'start_at' => ['sometimes', 'required', 'date'],
            'end_at' => ['required_if:status,COMPLETED', 'nullable', 'date', 'after_or_equal:start_at'],
            'end_meter_reading_km' => ['nullable', 'numeric', 'gt:0'],
            'load_weight_ton' => ['nullable', 'numeric', 'gt:0'],
            'planned_mileage_km_per_liter' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_load_ton' => ['nullable', 'numeric', 'min:0'],
            'planned_diesel_liters' => ['nullable', 'numeric', 'min:0'],
            'diesel_rate_per_liter' => ['nullable', 'numeric', 'min:0'],
            'cancelled_at' => ['required_if:status,CANCELLED', 'nullable', 'date'],
            'cancellation_reason' => ['required_if:status,CANCELLED', 'nullable', 'string', 'max:1000'],
            'actual_arrival_at' => ['required_if:status,COMPLETED', 'nullable', 'date', 'after_or_equal:start_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'route_legs' => ['sometimes', 'array', 'min:1'],
            'route_legs.*.from_station_id' => ['required_with:route_legs', 'integer', 'exists:stations,id'],
            'route_legs.*.to_station_id' => ['required_with:route_legs', 'integer', 'exists:stations,id'],
            'route_legs.*.customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'route_legs.*.goods_name' => ['nullable', 'string', 'max:120'],
            'route_legs.*.pickup_date' => ['nullable', 'date', 'after_or_equal:start_at'],
            'route_legs.*.no_of_units' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'route_legs.*.ewaybill_no' => ['nullable', 'string', 'max:64'],
            'route_legs.*.goods_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'route_legs.*.from_city' => ['nullable', 'string', 'max:120'],
            'route_legs.*.to_city' => ['nullable', 'string', 'max:120'],
            'route_legs.*.freight_rate' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'route_legs.*.weight_ton' => ['nullable', 'numeric', 'min:0'],
            'route_legs.*.expected_mileage_km_per_liter' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'route_legs.*.distance_km' => ['nullable', 'numeric', 'min:0'],
            'route_legs.*.sequence_no' => ['nullable', 'integer', 'min:1'],
            'route_legs.*.freight_amount' => ['nullable', 'numeric', 'min:0'],
            'route_legs.*.remarks' => ['nullable', 'string', 'max:1000'],
            'expenses' => ['nullable', 'array'],
            'expenses.*.expense_type' => ['required_with:expenses', Rule::enum(TripExpenseType::class)],
            'expenses.*.expense_date' => ['required_with:expenses', 'date'],
            'expenses.*.amount' => ['required_with:expenses', 'numeric', 'gt:0'],
            'expenses.*.fuel_odometer_km' => ['nullable', 'integer', 'min:0'],
            'expenses.*.fuel_liters' => ['nullable', 'decimal:0,2', 'gt:0', 'max:5000'],
            'expenses.*.fuel_rate' => ['nullable', 'decimal:0,2', 'gt:0', 'max:1000'],
            'expenses.*.route_leg_sequence' => ['nullable', 'integer', 'min:1'],
            'expenses.*.other_type' => ['nullable', 'string', 'max:120'],
            'expenses.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'total_distance_km.prohibited' => 'Total KM is calculated automatically from meter readings.',
            'start_meter_reading_km.required' => 'Start meter reading is required.',
            'start_meter_reading_km.gt' => 'Start meter reading must be greater than zero.',
            'end_meter_reading_km.gt' => 'End meter reading must be greater than zero.',
            'planned_mileage_km_per_liter.max' => 'Max 100 KM/Lt.',
            'route_legs.*.expected_mileage_km_per_liter.max' => 'Max 100 KM/Lt.',
            'route_legs.*.freight_rate.max' => 'Max ₹1,00,000/Ton.',
            'route_legs.*.pickup_date.after_or_equal' => 'Pickup date cannot be before the trip start date.',
            'route_legs.*.no_of_units.max' => 'Max 100,000 units allowed.',
        ];
    }
}
