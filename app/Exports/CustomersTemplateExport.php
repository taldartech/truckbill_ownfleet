<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomersTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'name',
            'gst_number',
            'pan_number',
            'contact_number',
            'address',
            'country',
            'state',
            'city',
            'is_broker',
        ];
    }

    public function array(): array
    {
        return [];
    }
}