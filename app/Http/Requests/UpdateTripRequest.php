<?php

namespace App\Http\Requests;

use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Http\Requests\Concerns\ValidatesTripRoutePickupSequence;

use App\Trip;
use App\TripExpenseTypeMaster;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTripRequest extends FormRequest
{
    use ValidatesTripRoutePickupSequence;

    public function authorize(): bool
    {
        $trip = $this->route('trip');

        return $trip !== null && ($this->user()?->can('update', $trip) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('start_meter_reading_km')) {
            return;
        }

        $startMeter = $this->input('start_meter_reading_km');

        if (
            $startMeter === ''
            || $startMeter === null
            || (is_numeric($startMeter) && (float) $startMeter <= 0)
        ) {
            $startMeter = null;
        }

        $this->merge([
            'start_meter_reading_km' => $startMeter,
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $trip = $this->route('trip');

        $effectiveStatus = strtoupper((string) (
            $this->input('status')
            ?? ($trip?->status instanceof TripStatus ? $trip->status->value : $trip?->status)
            ?? ''
        ));
        $startMeterRequired = $effectiveStatus !== '' && $effectiveStatus !== TripStatus::Pending->value;
        $isMultiPoint = strtoupper((string) (
            $trip?->trip_type instanceof TripType
                ? $trip->trip_type->value
                : ($trip?->trip_type ?? '')
        )) === TripType::MultiPoint->value;

        $isPending = ($trip?->status instanceof TripStatus ? $trip->status : TripStatus::tryFrom((string) $trip?->status)) === TripStatus::Pending;
        $hasExpenses = $trip?->expenses()->exists() ?? false;
        $canChangeVehicleAndDriver = $isPending && ! $hasExpenses;

        return [
            'vehicle_id' => $canChangeVehicleAndDriver
                ? ['sometimes', 'required', 'uuid', 'exists:vehicles,id']
                : ['prohibited'],
            'driver_id' => $canChangeVehicleAndDriver
                ? ['sometimes', 'nullable', 'uuid', 'exists:drivers,id']
                : ['prohibited'],
            'substitute_driver_id' => ['nullable', 'uuid', 'exists:drivers,id'],
            'trip_number' => ['prohibited'],
            'trip_type' => ['prohibited'],
            'start_meter_reading_km' => $startMeterRequired
                ? ['sometimes', 'required', 'numeric', 'gt:0']
                : ['nullable', 'numeric', 'gt:0'],
            'total_distance_km' => ['prohibited'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'broker_id' => ['nullable', 'integer', 'exists:brokers,id'],
            'start_station_id' => ['sometimes', 'required', 'integer', 'exists:stations,id'],
            'status' => ['sometimes', Rule::enum(TripStatus::class)],
            'start_at' => ['sometimes', 'required', 'date'],
            'end_at' => ['required_if:status,COMPLETED', 'nullable', 'date', 'after_or_equal:start_at'],
            'end_meter_reading_km' => ['nullable', 'numeric', 'gt:0'],
            'load_weight_ton' => ['nullable', 'numeric', 'gt:0'],
            'planned_mileage_km_per_liter' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'max_load_ton' => ['nullable', 'numeric', 'min:0'],
            'planned_diesel_liters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'diesel_rate_per_liter' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'cancelled_at' => ['required_if:status,CANCELLED', 'nullable', 'date'],
            'cancellation_reason' => ['required_if:status,CANCELLED', 'nullable', 'string', 'max:1000'],
            'actual_arrival_at' => ['required_if:status,COMPLETED', 'nullable', 'date', 'after_or_equal:start_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'route_legs' => [
                'sometimes',
                'array',
                Rule::when(fn () => ! $isMultiPoint, ['min:1']),
            ],
            'route_legs.*.from_station_id' => ['required_with:route_legs', 'integer', 'exists:stations,id'],
            'route_legs.*.to_station_id' => ['required_with:route_legs', 'integer', 'exists:stations,id'],
            'route_legs.*.customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'route_legs.*.goods_name' => ['nullable', 'string', 'max:120'],
            'route_legs.*.consignor' => ['nullable', 'string', 'max:120'],
            'route_legs.*.consignee' => ['nullable', 'string', 'max:120'],
            'route_legs.*.from_address' => ['nullable', 'string', 'max:1000'],
            'route_legs.*.to_address' => ['nullable', 'string', 'max:1000'],
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
            'pickups' => ['sometimes', 'array', 'min:1'],
            'pickups.*.pickup_code' => ['nullable', 'string', 'max:32'],
            'pickups.*.sequence_no' => ['nullable', 'integer', 'min:1'],
            'pickups.*.station_id' => ['required_with:pickups', 'integer', 'exists:stations,id'],
            'pickups.*.consignor' => ['nullable', 'string', 'max:120'],
            'pickups.*.from_address' => ['nullable', 'string', 'max:1000'],
            'pickups.*.goods_name' => ['nullable', 'string', 'max:120'],
            'pickups.*.no_of_units' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'pickups.*.goods_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'pickups.*.ewaybill_no' => ['nullable', 'string', 'max:64'],
            'pickups.*.ewaybill_expiry' => ['nullable', 'date'],
            'pickups.*.pickup_date' => [
                'nullable',
                'date',
                'after_or_equal:start_at',
                Rule::when(
                    fn () => filled($this->input('end_at')),
                    ['before_or_equal:end_at'],
                ),
            ],
            'pickups.*.weight_ton' => ['required_with:pickups', 'numeric', 'gt:0'],
            'pickups.*.drops' => ['required_with:pickups', 'array', 'min:1'],
            'pickups.*.drops.*.drop_code' => ['nullable', 'string', 'max:32'],
            'pickups.*.drops.*.to_station_id' => ['required', 'integer', 'exists:stations,id'],
            'pickups.*.drops.*.consignee' => ['nullable', 'string', 'max:120'],
            'pickups.*.drops.*.to_address' => ['nullable', 'string', 'max:1000'],
            'pickups.*.drops.*.to_city' => ['nullable', 'string', 'max:120'],
            'pickups.*.drops.*.weight_ton' => ['required', 'numeric', 'gt:0'],
            'pickups.*.drops.*.freight_rate' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'pickups.*.drops.*.freight_amount' => ['nullable', 'numeric', 'min:0'],
            'pickups.*.drops.*.distance_km' => ['nullable', 'numeric', 'min:0'],
            'pickups.*.drops.*.no_of_units' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'pickups.*.drops.*.ewaybill_no' => ['nullable', 'string', 'max:64'],
            'pickups.*.drops.*.ewaybill_expiry' => ['nullable', 'date'],
            'pickups.*.drops.*.goods_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'pickups.*.drops.*.lr_number' => ['nullable', 'string', 'max:64'],
            'pickups.*.drops.*.remarks' => ['nullable', 'string', 'max:1000'],
            'pickups.*.drops.*.expected_mileage_km_per_liter' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'expenses' => ['nullable', 'array'],
            'expenses.*.expense_type' => ['required_with:expenses', 'string'],
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
            'planned_mileage_km_per_liter.max' => 'Max 50 KM/Lt.',
            'route_legs.*.expected_mileage_km_per_liter.max' => 'Max 50 KM/Lt.',
            'planned_diesel_liters.max' => 'Max 10,000 Ltr.',
            'diesel_rate_per_liter.max' => 'Max ₹1,000/Ltr.',
            'route_legs.*.freight_rate.max' => 'Max ₹1,00,000/Ton.',
            'route_legs.*.distance_km.min' => 'Distance cannot be negative.',
            'route_legs.*.pickup_date.after_or_equal' => 'Pickup date cannot be before the trip start date.',
            'route_legs.*.pickup_date.before_or_equal' => 'Pickup date cannot be after the trip end date.',
            'route_legs.*.no_of_units.max' => 'Max 100,000 units allowed.',
        ];
    }
}
