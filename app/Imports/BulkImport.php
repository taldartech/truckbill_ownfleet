<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

abstract class BulkImport implements ToCollection, WithHeadingRow
{
    public int $total = 0;

    public int $successful = 0;

    public int $failed = 0;

    public array $errors = [];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $this->total++;

            // Row 1 contains the headings.
            $excelRow = $index + 2;

            $this->processRow(
                $row->toArray(),
                $excelRow,
            );
        }
    }

    abstract protected function processRow(
        array $row,
        int $excelRow,
    ): void;
}