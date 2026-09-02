<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TyresTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'serial_number',
            'brand',
            'size',
            'purchase_date',
            'purchase_cost',
            'warranty_valid_until',
            'warranty_km',
            'tyre_type',
            'current_km',
            'expected_life_km',
            'notes',
        ];
    }

    public function array(): array
    {
        return [];
    }
}