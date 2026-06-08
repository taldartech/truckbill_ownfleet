<?php

namespace App\Http\Requests;

use App\DriverAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDriverAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var DriverAssignment|null $driverAssignment */
        $driverAssignment = $this->route('driverAssignment');

        if ($driverAssignment !== null) {
            return $this->user()?->can('update', $driverAssignment) ?? false;
        }

        return $this->user()?->can('create', DriverAssignment::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'uuid', Rule::exists('drivers', 'id')->where('is_active', true)],
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'assigned_from' => ['required', 'date'],
            'assigned_to' => ['prohibited'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
