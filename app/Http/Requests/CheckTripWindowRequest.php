<?php

namespace App\Http\Requests;

use App\Trip;
use Illuminate\Foundation\Http\FormRequest;

class CheckTripWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Trip::class) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'uuid', 'exists:drivers,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'ignore_trip_id' => ['nullable', 'uuid', 'exists:trips,id'],
        ];
    }
}
