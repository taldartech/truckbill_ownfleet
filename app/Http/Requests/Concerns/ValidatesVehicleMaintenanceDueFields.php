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
            $this->validateNextDueKmAfterMeterReading($validator);
        });
    }

    protected function validateNextDueDateAfterServiceDate(Validator $validator): void
    {
        $nextDueDate = $this->input('next_service_due_date');

        if ($nextDueDate === null || $nextDueDate === '') {
            return;
        }

        if ($validator->errors()->has('next_service_due_date')) {
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

    protected function validateNextDueKmAfterMeterReading(Validator $validator): void
    {
        $nextDueKm = $this->input('next_service_due_km');

        if ($nextDueKm === null || $nextDueKm === '') {
            return;
        }

        if ($validator->errors()->has('next_service_due_km')) {
            return;
        }

        $meterReading = $this->input('odometer_km');

        if ($meterReading === null || $meterReading === '') {
            $maintenance = $this->route('vehicle_maintenance');

            if ($maintenance instanceof VehicleMaintenance) {
                $meterReading = $maintenance->odometer_km;
            }
        }

        if (is_numeric($meterReading) && (float) $nextDueKm <= (float) $meterReading) {
            $validator->errors()->add(
                'next_service_due_km',
                'Next due KM must be greater than the meter reading.',
            );
        }
    }
}
