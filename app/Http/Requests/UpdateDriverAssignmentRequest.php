<?php

namespace App\Http\Requests;

use App\DriverAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var DriverAssignment|null $driverAssignment */
        $driverAssignment = $this->route('driver_assignment');

        return $driverAssignment !== null
            && ($this->user()?->can('update', $driverAssignment) ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var DriverAssignment $driverAssignment */
        $driverAssignment = $this->route('driver_assignment');
        $isClosedAssignment = $driverAssignment->assigned_to !== null;

        return [
            'driver_id' => ['required', 'uuid', Rule::exists('drivers', 'id')],
            'vehicle_id' => ['required', 'uuid', 'exists:vehicles,id'],
            'assigned_from' => ['required', 'date'],
            'assigned_to' => [
                $isClosedAssignment ? 'nullable' : 'prohibited',
                'date',
                'after_or_equal:assigned_from',
            ],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
