<?php

namespace App\Http\Requests\Concerns;

use App\VehicleMaintenance;
use Illuminate\Validation\Validator;

trait ValidatesVehicleMaintenanceDueFields
{
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateNextDueDateAfterServiceDate($validator);
        });
    }

    protected function validateNextDueDateAfterServiceDate(Validator $validator): void
    {
        $nextDueDate = $this->input('next_service_due_date');

        if ($nextDueDate === null || $nextDueDate === '') {
            return;
        }

        $serviceDate = $this->input('service_date');

        if ($serviceDate === null || $serviceDate === '') {
            $maintenance = $this->route('vehicle_maintenance');

            if ($maintenance instanceof VehicleMaintenance) {
                $serviceDate = $maintenance->service_date?->toDateString();
            }
        }

        if ($serviceDate !== null && $nextDueDate <= $serviceDate) {
            $validator->errors()->add(
                'next_service_due_date',
                'Next due date must be after service date.',
            );
        }
    }
}
