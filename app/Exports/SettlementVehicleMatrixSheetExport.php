<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SettlementVehicleMatrixSheetExport implements FromArray, WithHeadings, WithTitle
{
    private readonly int $calendarDays;

    /**
     * @param  array<string, mixed>  $sheet
     */
    public function __construct(private readonly array $sheet)
    {
        $this->calendarDays = (int) ($sheet['calendar_days'] ?? 30);
    }

    public function title(): string
    {
        return 'Truck Attendance';
    }

    /**
     * @return list<string|int>
     */
    public function headings(): array
    {
        return [
            ...['S.No.', 'Truck No.', 'Driver'],
            ...range(1, $this->calendarDays),
            'Salary',
            'Remarks',
            'Total',
        ];
    }

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        $rows = [];

        foreach ($this->sheet['vehicle_matrix'] ?? [] as $row) {
            /** @var array<int|string, string> $days */
            $days = $row['days'] ?? [];
            $dayMarks = [];

            for ($day = 1; $day <= $this->calendarDays; $day++) {
                $mark = $days[$day] ?? $days[(string) $day] ?? '';
                $dayMarks[] = $mark !== '' ? $mark : '·';
            }

            $rows[] = [
                $row['serial'] ?? '',
                $row['vehicle_label'] ?? '',
                $row['driver_name'] ?? '',
                ...$dayMarks,
                round((float) ($row['salary'] ?? 0), 2),
                $row['remarks'] ?? '',
                round((float) ($row['total'] ?? 0), 2),
            ];
        }

        return $rows;
    }
}
