<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DriversTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'name',
            'phone',
            'license_number',
            'license_expiry_date',
            'address',
            'aadhaar_number',
            'pan_number',
            'bank_account_number',
            'bank_ifsc',
            'joining_date',
            'emergency_contact_name',
            'emergency_contact_phone',
            'monthly_base_salary',
            'is_active',
        ];
    }

    public function array(): array
    {
        return [];
    }
}