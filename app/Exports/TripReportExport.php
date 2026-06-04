<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TripReportExport implements FromArray, WithHeadings
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(private readonly array $rows) {}

    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        return array_map(
            fn (array $row): array => array_values($row),
            $this->rows,
        );
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        if ($this->rows === []) {
            return [];
        }

        return array_keys($this->rows[0]);
    }
}
