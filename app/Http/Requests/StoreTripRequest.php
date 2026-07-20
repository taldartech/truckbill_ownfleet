<?php

namespace App\Http\Requests;

use App\Enums\TripExpenseType;
use App\Enums\TripType;
use App\Http\Requests\Concerns\ValidatesTripRoutePickupSequence;
use App\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTripRequest extends FormRequest
{
    use ValidatesTripRoutePickupSequence;
    public function authorize(): bool
    {
        return $this->user()?->can('create', Trip::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $startMeter = $this->input('start_meter_reading_km');

        if (
            $startMeter === ''
            || $startMeter === null
            || (is_numeric($startMeter) && (float) $startMeter <= 0)
        ) {
            $startMeter = null;
        }

        $this->merge([
            'trip_number' => null,
            'start_meter_reading_km' => $startMeter,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'uuid', 'exists:drivers,id'],
            'substitute_driver_id' => ['nullable', 'uuid', 'exists:drivers,id'],
            'trip_number' => ['prohibited'],
            'trip_sequence' => ['nullable', 'integer', 'min:1', 'max:999999999'],
            'trip_type' => ['required', Rule::enum(TripType::class)],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'broker_id' => ['nullable', 'integer', 'exists:brokers,id'],
            'start_station_id' => ['required', 'integer', 'exists:stations,id'],
            'status' => ['prohibited'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'end_meter_reading_km' => ['nullable', 'numeric', 'gt:0'],
            'total_distance_km' => ['prohibited'],
            'start_meter_reading_km' => ['nullable', 'numeric', 'gt:0'],
            'load_weight_ton' => ['nullable', 'numeric', 'gt:0'],
            'planned_mileage_km_per_liter' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'max_load_ton' => ['nullable', 'numeric', 'min:0'],
            'planned_diesel_liters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'diesel_rate_per_liter' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'route_legs' => ['required', 'array', 'min:1'],
            'route_legs.*.from_station_id' => ['required', 'integer', 'exists:stations,id'],
            'route_legs.*.to_station_id' => ['required', 'integer', 'exists:stations,id'],
            'route_legs.*.customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'route_legs.*.goods_name' => ['nullable', 'string', 'max:120'],
            'route_legs.*.consignor' => ['nullable', 'string', 'max:120'],
            'route_legs.*.consignee' => ['nullable', 'string', 'max:120'],
            'route_legs.*.pickup_date' => [
                'nullable',
                'date',
                'after_or_equal:start_at',
                Rule::when(
                    fn () => filled($this->input('end_at')),
                    ['before_or_equal:end_at'],
                ),
            ],
            'route_legs.*.no_of_units' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'route_legs.*.ewaybill_no' => ['nullable', 'string', 'max:64'],
            'route_legs.*.ewaybill_expiry' => ['nullable', 'date'],
            'route_legs.*.goods_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'route_legs.*.from_city' => ['nullable', 'string', 'max:120'],
            'route_legs.*.to_city' => ['nullable', 'string', 'max:120'],
            'route_legs.*.freight_rate' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'route_legs.*.weight_ton' => ['nullable', 'numeric', 'min:0'],
            'route_legs.*.expected_mileage_km_per_liter' => ['nullable', 'numeric', 'min:0', 'max:50'],
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
            'trip_number.prohibited' => 'Trip number is auto-generated by the system.',
            'trip_sequence.min' => 'Trip sequence must be at least 1.',
            'status.prohibited' => 'Trip status is set automatically when creating a trip.',
            'total_distance_km.prohibited' => 'Total KM is calculated automatically from meter readings.',
            'end_meter_reading_km.gt' => 'End meter reading must be greater than zero.',
            'start_meter_reading_km.required' => 'Start meter reading is required.',
            'start_meter_reading_km.gt' => 'Start meter reading must be greater than zero.',
            'start_station_id.required' => 'Select the start station for this trip.',
            'start_at.required' => 'Trip start date is required.',
            'route_legs.required' => 'Add at least one route stop for this trip.',
            'route_legs.min' => 'Add at least one route stop for this trip.',
            'planned_mileage_km_per_liter.max' => 'Max 50 KM/Lt.',
            'route_legs.*.expected_mileage_km_per_liter.max' => 'Max 50 KM/Lt.',
            'planned_diesel_liters.max' => 'Max 10,000 Ltr.',
            'diesel_rate_per_liter.max' => 'Max ₹1,000/Ltr.',
            'route_legs.*.freight_rate.max' => 'Max ₹1,00,000/Ton.',
            'route_legs.*.distance_km.min' => 'Distance cannot be negative.',
            'route_legs.*.pickup_date.after_or_equal' => 'Pickup date cannot be before the trip start date.',
            'route_legs.*.pickup_date.before_or_equal' => 'Pickup date cannot be after the trip end date.',
            'route_legs.*.no_of_units.max' => 'Max 100,000 units allowed.',
            'expenses.*.amount.gt' => 'Expense amount must be greater than zero.',
        ];
    }
}
