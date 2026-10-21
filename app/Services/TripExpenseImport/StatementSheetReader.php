<?php

namespace App\Services\TripExpenseImport;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;

class StatementSheetReader
{
    public const MAX_ROWS = 2000;

    /**
     * @return array{
     *     rows: list<list<string|null>>,
     *     max_columns: int
     * }
     */
    public function read(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if (! $path || ! is_file($path)) {
            throw new RuntimeException('Uploaded file could not be read.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        if (! in_array($extension, ['csv', 'txt', 'xlsx', 'xls'], true)) {
            throw new RuntimeException('Unsupported file format. Please upload a CSV (.csv/.txt) or Excel (.xlsx/.xls) file.');
        }

        $tempCsvPath = null;

        try {
            if (in_array($extension, ['csv', 'txt'], true)) {
                $tempCsvPath = $this->copyAsCsvTemp($path);
                $reader = new Csv;
                $reader->setInputEncoding('UTF-8');
                $reader->setDelimiter($this->detectDelimiter($tempCsvPath));
                $spreadsheet = $reader->load($tempCsvPath);
            } elseif ($extension === 'xlsx') {
                $reader = new Xlsx;
                $spreadsheet = $reader->load($path);
            } else {
                $reader = new Xls;
                $spreadsheet = $reader->load($path);
            }

            $worksheet = $spreadsheet->getActiveSheet();
            $maxCol = $worksheet->getHighestColumn();
            $maxRow = min((int) $worksheet->getHighestRow(), self::MAX_ROWS + 50);
            $allRows = $worksheet->rangeToArray("A1:{$maxCol}{$maxRow}", null, true, true, false);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $normalized = [];
            $maxColumns = 0;

            foreach ($allRows as $row) {
                $cells = array_map(static function ($cell) {
                    if ($cell === null) {
                        return null;
                    }

                    if (is_float($cell) || is_int($cell)) {
                        return $cell;
                    }

                    $trimmed = trim((string) $cell);

                    return $trimmed === '' ? null : $trimmed;
                }, array_values($row));

                while ($cells !== [] && end($cells) === null) {
                    array_pop($cells);
                }

                if ($cells === []) {
                    $normalized[] = [];

                    continue;
                }

                $maxColumns = max($maxColumns, count($cells));
                $normalized[] = $cells;
            }

            return [
                'rows' => $normalized,
                'max_columns' => $maxColumns,
            ];
        } finally {
            if ($tempCsvPath && is_file($tempCsvPath)) {
                @unlink($tempCsvPath);
            }
        }
    }

    private function copyAsCsvTemp(string $path): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'stmt_');
        if ($temp === false) {
            throw new RuntimeException('Unable to create a temporary file for CSV parsing.');
        }

        $csvPath = $temp.'.csv';
        if (! @rename($temp, $csvPath)) {
            @unlink($temp);
            throw new RuntimeException('Unable to prepare CSV temporary file.');
        }

        if (! @copy($path, $csvPath)) {
            @unlink($csvPath);
            throw new RuntimeException('Unable to copy uploaded CSV for parsing.');
        }

        return $csvPath;
    }

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ',';
        }

        $line = fgets($handle) ?: '';
        fclose($handle);

        $comma = substr_count($line, ',');
        $semi = substr_count($line, ';');
        $tab = substr_count($line, "\t");
        $pipe = substr_count($line, '|');

        $counts = [',' => $comma, ';' => $semi, "\t" => $tab, '|' => $pipe];
        arsort($counts);
        $best = array_key_first($counts);

        return ($counts[$best] ?? 0) > 0 ? (string) $best : ',';
    }
}
