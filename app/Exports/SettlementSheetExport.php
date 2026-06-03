<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SettlementSheetExport implements WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $sheet
     */
    public function __construct(private readonly array $sheet) {}

    /**
     * @return list<SettlementPayrollSheetExport|SettlementVehicleMatrixSheetExport>
     */
    public function sheets(): array
    {
        return [
            new SettlementPayrollSheetExport($this->sheet),
            new SettlementVehicleMatrixSheetExport($this->sheet),
        ];
    }
}
