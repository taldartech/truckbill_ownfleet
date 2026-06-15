<?php

namespace App\Http\Requests;

use App\Enums\RouteType;
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
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'broker_id' => ['nullable', 'integer', 'exists:brokers,id'],
            'start_station_id' => ['sometimes', 'required', 'integer', 'exists:stations,id'],
            'status' => ['sometimes', Rule::enum(TripStatus::class)],
            'start_at' => ['sometimes', 'required', 'date'],
            'end_at' => ['required_if:status,COMPLETED', 'nullable', 'date', 'after_or_equal:start_at'],
            'cancelled_at' => ['required_if:status,CANCELLED', 'nullable', 'date'],
            'cancellation_reason' => ['required_if:status,CANCELLED', 'nullable', 'string', 'max:1000'],
            'actual_arrival_at' => ['required_if:status,COMPLETED', 'nullable', 'date', 'after_or_equal:start_at'],
            'route_legs' => ['sometimes', 'array', 'min:1'],
            'route_legs.*.from_station_id' => ['required_with:route_legs', 'integer', 'exists:stations,id'],
            'route_legs.*.to_station_id' => ['required_with:route_legs', 'integer', 'exists:stations,id'],
            'route_legs.*.customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'route_legs.*.from_city' => ['nullable', 'string', 'max:120'],
            'route_legs.*.to_city' => ['nullable', 'string', 'max:120'],
            'route_legs.*.route_type' => ['required_with:route_legs', Rule::enum(RouteType::class)],
            'route_legs.*.distance_km' => ['required_with:route_legs', 'numeric', 'gt:0'],
            'route_legs.*.sequence_no' => ['nullable', 'integer', 'min:1'],
            'route_legs.*.freight_amount' => ['nullable', 'numeric', 'min:0'],
            'route_legs.*.remarks' => ['nullable', 'string', 'max:1000'],
            'expenses' => ['nullable', 'array'],
            'expenses.*.expense_type' => ['required_with:expenses', Rule::enum(TripExpenseType::class)],
            'expenses.*.expense_date' => ['required_with:expenses', 'date'],
            'expenses.*.amount' => ['required_with:expenses', 'numeric', 'gt:0'],
            'expenses.*.distance_km' => ['nullable', 'numeric', 'min:0'],
            'expenses.*.fuel_liters' => ['nullable', 'numeric', 'min:0'],
            'expenses.*.fuel_rate' => ['nullable', 'numeric', 'min:0'],
            'expenses.*.odometer_start' => ['nullable', 'numeric', 'min:0'],
            'expenses.*.odometer_end' => ['nullable', 'numeric', 'min:0'],
            'expenses.*.route_leg_sequence' => ['nullable', 'integer', 'min:1'],
            'expenses.*.other_type' => ['nullable', 'string', 'max:120'],
            'expenses.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
