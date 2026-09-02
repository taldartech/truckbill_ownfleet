<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VehiclesTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'registration_number',
            'vehicle_type',
            'tyre_layout',
            'make',
            'model',
            'manufacture_year',
            'odometer_km',
            'default_mileage_km_per_liter',
            'max_load_ton',
            'fuel_tank_capacity_ltr',
            'is_active',
        ];
    }

    public function array(): array
    {
        return [];
    }
}