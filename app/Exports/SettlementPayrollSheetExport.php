<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SettlementPayrollSheetExport implements FromArray, WithHeadings, WithTitle
{
    /**
     * @param  array<string, mixed>  $sheet
     */
    public function __construct(private readonly array $sheet) {}

    public function title(): string
    {
        return 'Payroll Register';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'S.No.',
            'Name',
            'Aadhaar',
            'Bank A/C',
            'IFSC',
            'Pay Days',
            'P',
            'A',
            'L',
            'Sub.P',
            'Sub.Pay',
            'Gross',
            'Absent Ded.',
            'Base',
            'Extra Ded.',
            'Net',
            'Rnd',
            'Remarks',
            'Status',
        ];
    }

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        $rows = [];

        foreach ($this->sheet['payroll_lines'] ?? [] as $line) {
            $name = (string) ($line['name'] ?? '');

            if (! empty($line['is_substitute'])) {
                $name .= ' (Substitute)';
            }

            $rows[] = [
                $line['serial'] ?? '',
                $name,
                $line['aadhaar_number'] ?? '',
                $line['bank_account_number'] ?? '',
                $line['bank_ifsc'] ?? '',
                $line['pay_days'] ?? '',
                $line['present_days'] ?? 0,
                $line['absent_days'] ?? 0,
                $line['leave_days'] ?? 0,
                $line['substitute_days'] ?? 0,
                round((float) ($line['substitute_pay'] ?? 0), 2),
                round((float) ($line['gross_earning'] ?? 0), 2),
                round((float) ($line['absent_deduction'] ?? 0), 2),
                round((float) ($line['base_salary'] ?? 0), 2),
                round((float) ($line['extra_deductions'] ?? 0), 2),
                round((float) ($line['net_salary'] ?? 0), 2),
                ! empty($line['round_net']) ? 'Yes' : 'No',
                $line['remarks'] ?? '',
                ($line['payment_status'] ?? 'unpaid') === 'paid' ? 'Paid' : 'Unpaid',
            ];
        }

        return $rows;
    }
}
