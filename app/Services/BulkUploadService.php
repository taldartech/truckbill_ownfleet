<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkUploadService
{
    /**
     * Chunk size for batch processing.
     */
    private const CHUNK_SIZE = 250;

    /**
     * Maximum detailed errors to retain in memory and return in the response.
     */
    private const MAX_ERRORS = 1000;

    /**
     * Process an uploaded CSV/Excel file against a model and column specification.
     *
     * @param  class-string  $modelClass
     * @param  array<string, array{
     *     label: string,
     *     required?: bool,
     *     aliases?: list<string>,
     *     rules?: list<string>|string,
     *     unique?: bool,
     *     transform?: callable
     * }>  $columnSpecs
     * @return array{
     *     success: bool,
     *     summary: array{
     *         total: int,
     *         successful: int,
     *         failed: int,
     *         skipped: int
     *     },
     *     errors: list<array{
     *         row: int,
     *         field: string,
     *         value: string,
     *         message: string
     *     }>,
     *     message: string
     * }
     */
    public function process(
        UploadedFile $file,
        string $modelClass,
        array $columnSpecs,
        ?callable $beforeSave = null,
        ?callable $rowValidator = null,
        ?callable $saveHandler = null
    ): array {
        $path = $file->getRealPath();
        if (! $path || ! file_exists($path)) {
            return [
                'success' => false,
                'summary' => ['total' => 0, 'successful' => 0, 'failed' => 0, 'skipped' => 0],
                'errors' => [
                    [
                        'row' => 1,
                        'field' => 'File',
                        'value' => $file->getClientOriginalName(),
                        'message' => 'Uploaded file could not be read or is empty.',
                    ],
                ],
                'message' => 'Failed to read uploaded file.',
            ];
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['csv', 'txt', 'xlsx', 'xls'], true)) {
            return [
                'success' => false,
                'summary' => ['total' => 0, 'successful' => 0, 'failed' => 0, 'skipped' => 0],
                'errors' => [
                    [
                        'row' => 1,
                        'field' => 'File',
                        'value' => $file->getClientOriginalName(),
                        'message' => 'Unsupported file format. Please upload a CSV (.csv) or Excel (.xlsx, .xls) file.',
                    ],
                ],
                'message' => 'Unsupported file format. Please upload a CSV or Excel file.',
            ];
        }

        $sheetData = $this->readSheet($path, $extension);
        if (! $sheetData || empty($sheetData['headers'])) {
            return [
                'success' => false,
                'summary' => ['total' => 0, 'successful' => 0, 'failed' => 0, 'skipped' => 0],
                'errors' => [
                    [
                        'row' => 1,
                        'field' => 'Header',
                        'value' => '',
                        'message' => 'The uploaded file is empty or does not contain a valid header row.',
                    ],
                ],
                'message' => 'The uploaded file does not contain valid headers.',
            ];
        }

        $rawHeaders = $sheetData['headers'];
        $rows = $sheetData['rows'];

        $headerMap = $this->mapHeaders($rawHeaders, $columnSpecs);

        // Verify that all required columns are present in the header
        $missingRequired = [];
        foreach ($columnSpecs as $field => $spec) {
            if (! empty($spec['required']) && ! isset($headerMap[$field])) {
                $missingRequired[] = $spec['label'] ?? $field;
            }
        }

        if (! empty($missingRequired)) {
            return [
                'success' => false,
                'summary' => ['total' => 0, 'successful' => 0, 'failed' => 0, 'skipped' => 0],
                'errors' => [
                    [
                        'row' => 1,
                        'field' => 'Header',
                        'value' => implode(', ', $rawHeaders),
                        'message' => 'Missing required column(s): ' . implode(', ', $missingRequired) . '. Please use the downloaded template.',
                    ],
                ],
                'message' => 'Missing required column(s): ' . implode(', ', $missingRequired) . '.',
            ];
        }

        $total = 0;
        $successful = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];
        $errorCount = 0;

        // Track seen unique values across the entire uploaded file batch
        $seenInBatch = [];
        foreach ($columnSpecs as $field => $spec) {
            if (! empty($spec['unique'])) {
                $seenInBatch[$field] = [];
            }
        }

        $currentChunk = [];
        $rowNumber = 1; // 1 is header row

        foreach ($rows as $row) {
            $rowNumber++;

            // Check if entire row is empty (whitespace only or empty cells)
            if ($this->isRowEmpty($row)) {
                $skipped++;

                continue;
            }

            $total++;
            $rowErrors = [];
            $rowData = [];
            $rawValues = [];

            // Extract mapped fields
            foreach ($columnSpecs as $field => $spec) {
                $colIndex = $headerMap[$field] ?? null;
                $rawVal = ($colIndex !== null && isset($row[$colIndex])) ? trim((string) $row[$colIndex]) : '';

                if (isset($spec['transform']) && is_callable($spec['transform'])) {
                    $val = ($spec['transform'])($rawVal);
                } else {
                    $val = $rawVal;
                }

                $rowData[$field] = $val;
                $rawValues[$field] = $rawVal;

                // Validation rules
                if (! empty($spec['rules'])) {
                    $validator = Validator::make(
                        [$field => $val],
                        [$field => $spec['rules']],
                        [],
                        [$field => $spec['label'] ?? $field]
                    );

                    if ($validator->fails()) {
                        foreach ($validator->errors()->all() as $msg) {
                            $rowErrors[] = [
                                'row' => $rowNumber,
                                'field' => $spec['label'] ?? $field,
                                'value' => $rawVal,
                                'message' => $msg,
                            ];
                        }
                    }
                }

                // Check uniqueness across the entire uploaded file
                if (! empty($spec['unique']) && $val !== '' && $val !== null) {
                    $normalizedVal = mb_strtolower((string) $val, 'UTF-8');
                    if (isset($seenInBatch[$field][$normalizedVal])) {
                        $prevRow = $seenInBatch[$field][$normalizedVal];
                        $rowErrors[] = [
                            'row' => $rowNumber,
                            'field' => $spec['label'] ?? $field,
                            'value' => $rawVal,
                            'message' => sprintf(
                                'Duplicate %s "%s" in uploaded file (first seen on row %d).',
                                $spec['label'] ?? $field,
                                (string) $val,
                                $prevRow
                            ),
                        ];
                    } else {
                        $seenInBatch[$field][$normalizedVal] = $rowNumber;
                    }
                }
            }

            // Custom row-level validator/hook
            if (empty($rowErrors) && $rowValidator !== null) {
                $customErrors = $rowValidator($rowData, $rowNumber);
                if (! empty($customErrors) && is_array($customErrors)) {
                    foreach ($customErrors as $err) {
                        if (is_array($err)) {
                            $rowErrors[] = $err;
                        } else {
                            $rowErrors[] = [
                                'row' => $rowNumber,
                                'field' => 'Row',
                                'value' => '',
                                'message' => (string) $err,
                            ];
                        }
                    }
                }
            }

            if (! empty($rowErrors)) {
                $failed++;
                foreach ($rowErrors as $err) {
                    $this->addError($errors, $errorCount, $err);
                }
            } else {
                $currentChunk[] = [
                    'row' => $rowNumber,
                    'data' => $rowData,
                    'rawValues' => $rawValues,
                ];

                if (count($currentChunk) >= self::CHUNK_SIZE) {
                    $this->processChunk(
                        $currentChunk,
                        $modelClass,
                        $columnSpecs,
                        $beforeSave,
                        $saveHandler,
                        $successful,
                        $failed,
                        $errors,
                        $errorCount
                    );
                    $currentChunk = [];
                }
            }
        }

        // Process final partial chunk if any
        if (! empty($currentChunk)) {
            $this->processChunk(
                $currentChunk,
                $modelClass,
                $columnSpecs,
                $beforeSave,
                $saveHandler,
                $successful,
                $failed,
                $errors,
                $errorCount
            );
            $currentChunk = [];
        }

        // Sort errors by row number for consistent presentation
        if (! empty($errors)) {
            usort($errors, fn(array $a, array $b): int => ($a['row'] ?? 0) <=> ($b['row'] ?? 0));
        }

        $message = sprintf(
            'Processed %d row%s: %d imported successfully, %d failed, %d empty row%s skipped.',
            $total,
            $total === 1 ? '' : 's',
            $successful,
            $failed,
            $skipped,
            $skipped === 1 ? '' : 's'
        );

        if ($errorCount > count($errors)) {
            $omitted = $errorCount - count($errors);
            $message .= sprintf(
                ' Note: Only the first %d errors are listed (%d additional error%s omitted).',
                count($errors),
                $omitted,
                $omitted === 1 ? '' : 's'
            );
        }

        return [
            'success' => $failed === 0,
            'summary' => [
                'total' => $total,
                'successful' => $successful,
                'failed' => $failed,
                'skipped' => $skipped,
            ],
            'errors' => $errors,
            'message' => $message,
        ];
    }

    /**
     * Process a single chunk of candidate rows:
     * 1. Batch database uniqueness checks using whereIn().
     * 2. Persist valid records in a chunk-level database transaction.
     *
     * @param  list<array{row: int, data: array<string, mixed>, rawValues: array<string, string>}>  $chunk
     * @param  class-string  $modelClass
     * @param  array<string, array<string, mixed>>  $columnSpecs
     * @param  list<array{row: int, field: string, value: string, message: string}>  $errors
     */
    private function processChunk(
        array $chunk,
        string $modelClass,
        array $columnSpecs,
        ?callable $beforeSave,
        ?callable $saveHandler,
        int &$successful,
        int &$failed,
        array &$errors,
        int &$errorCount
    ): void {
        if (empty($chunk)) {
            return;
        }

        // 1. Batch database uniqueness checks per unique column
        $dbExistingLookup = [];

        foreach ($columnSpecs as $field => $spec) {
            if (empty($spec['unique'])) {
                continue;
            }

            $valuesToQuery = [];
            foreach ($chunk as $item) {
                $val = $item['data'][$field] ?? '';
                if ($val !== '' && $val !== null) {
                    $valuesToQuery[] = (string) $val;
                }
            }

            if (empty($valuesToQuery)) {
                continue;
            }

            $uniqueValues = array_values(array_unique($valuesToQuery));

            // Query DB once for this chunk using whereIn, preserving model global scopes (e.g. tenant/branch)
            $existingValues = $modelClass::query()
                ->whereIn($field, $uniqueValues)
                ->pluck($field)
                ->all();

            $lookup = [];
            foreach ($existingValues as $ev) {
                $evStr = (string) $ev;
                $lookup[$evStr] = true;
                $lookup[mb_strtolower($evStr, 'UTF-8')] = true;
            }

            $dbExistingLookup[$field] = $lookup;
        }

        // Separate rows into valid to-save and DB-uniqueness-failed
        $toSave = [];

        foreach ($chunk as $item) {
            $rowNumber = $item['row'];
            $rowData = $item['data'];
            $rawValues = $item['rawValues'];
            $rowErrors = [];

            foreach ($columnSpecs as $field => $spec) {
                if (empty($spec['unique'])) {
                    continue;
                }

                $val = $rowData[$field] ?? '';
                if ($val === '' || $val === null) {
                    continue;
                }

                $valStr = (string) $val;
                $normalizedVal = mb_strtolower($valStr, 'UTF-8');

                $lookup = $dbExistingLookup[$field] ?? [];
                if (isset($lookup[$valStr]) || isset($lookup[$normalizedVal])) {
                    $rawVal = (string) ($rawValues[$field] ?? $valStr);
                    $rowErrors[] = [
                        'row' => $rowNumber,
                        'field' => $spec['label'] ?? $field,
                        'value' => $rawVal,
                        'message' => sprintf(
                            '%s "%s" already exists in records.',
                            $spec['label'] ?? $field,
                            $valStr
                        ),
                    ];
                }
            }

            if (! empty($rowErrors)) {
                $failed++;
                foreach ($rowErrors as $err) {
                    $this->addError($errors, $errorCount, $err);
                }
            } else {
                $toSave[] = $item;
            }
        }

        if (empty($toSave)) {
            return;
        }

        // 2. Persist valid records in a chunk-level database transaction
        $chunkSuccessfulBefore = $successful;

        try {
            DB::transaction(function () use (
                $toSave,
                $modelClass,
                $beforeSave,
                $saveHandler,
                &$successful,
                &$failed,
                &$errors,
                &$errorCount
            ) {
                foreach ($toSave as $item) {
                    try {
                        $data = $item['data'];
                        if ($beforeSave) {
                            $data = $beforeSave($data, $item['row']);
                        }
                        if ($saveHandler) {
                            $saveHandler($data, $item['row']);
                        } else {
                            $modelClass::create($data);
                        }
                        $successful++;
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->addError($errors, $errorCount, [
                            'row' => $item['row'],
                            'field' => 'Database',
                            'value' => json_encode($item['data']),
                            'message' => 'Database save error: ' . $e->getMessage(),
                        ]);
                    }
                }
            });
        } catch (\Throwable $e) {
            $rolledBackCount = $successful - $chunkSuccessfulBefore;
            $successful = $chunkSuccessfulBefore;
            $failed += $rolledBackCount;
            $this->addError($errors, $errorCount, [
                'row' => $toSave[0]['row'] ?? 0,
                'field' => 'Transaction',
                'value' => '',
                'message' => 'Database transaction error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Record an error while respecting memory limits.
     *
     * @param  list<array{row: int, field: string, value: string, message: string}>  $errors
     * @param  array{row: int, field: string, value: string, message: string}  $error
     */
    private function addError(array &$errors, int &$errorCount, array $error): void
    {
        $errorCount++;

        if (count($errors) < self::MAX_ERRORS) {
            $errors[] = $error;
        }
    }

    public function downloadTemplate(array $headers, string $fileName, array $requiredHeaders = []): StreamedResponse
    {
        if (! empty($requiredHeaders)) {
            $requiredSet = [];
            $optionalSet = [];
            $normalizedRequired = array_map(fn ($h) => strtolower(trim((string) $h)), $requiredHeaders);

            foreach ($headers as $header) {
                if (in_array(strtolower(trim((string) $header)), $normalizedRequired, true)) {
                    $requiredSet[] = $header;
                } else {
                    $optionalSet[] = $header;
                }
            }
            $headers = array_merge($requiredSet, $optionalSet);
        }

        $format = request()->query('format');
        $isCsv = $format === 'csv';

        if ($isCsv) {
            $csvFileName = preg_replace('/\.(xlsx|xls)$/i', '.csv', $fileName);
            if (! str_ends_with(strtolower($csvFileName), '.csv')) {
                $csvFileName .= '.csv';
            }

            $responseHeaders = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $csvFileName . '"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ];

            return response()->stream(function () use ($headers) {
                $handle = fopen('php://output', 'w');

                // UTF-8 BOM for Microsoft Excel compatibility
                fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

                fputcsv($handle, $headers);

                fclose($handle);
            }, 200, $responseHeaders);
        }

        $xlsxFileName = preg_replace('/\.csv$/i', '.xlsx', $fileName);
        if (! str_ends_with(strtolower($xlsxFileName), '.xlsx')) {
            $xlsxFileName .= '.xlsx';
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template');

        $colIndex = 1;
        $normalizedRequired = array_map(fn ($h) => strtolower(trim((string) $h)), $requiredHeaders);

        foreach ($headers as $header) {
            $coordinate = Coordinate::stringFromColumnIndex($colIndex) . '1';
            $sheet->setCellValue($coordinate, $header);

            $isRequired = empty($requiredHeaders)
                || in_array(strtolower(trim((string) $header)), $normalizedRequired, true);

            $style = $sheet->getStyle($coordinate);
            $style->getFont()->setBold(true);

            if ($isRequired) {
                // Green background for required fields (#28A745)
                $style->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FF28A745');
                $style->getFont()->getColor()->setARGB('FFFFFFFF');
            } else {
                // Light neutral gray for optional fields (#E2E8F0)
                $style->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB('FFE2E8F0');
                $style->getFont()->getColor()->setARGB('FF1E293B');
            }

            $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $style->getBorders()->getAllBorders()->getColor()->setARGB('FFCBD5E1');

            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);

            $colIndex++;
        }

        $responseHeaders = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $xlsxFileName . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($spreadsheet) {
            $writer = new XlsxWriter($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }, 200, $responseHeaders);
    }

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return ',';
        }

        $line = fgets($handle);
        fclose($handle);

        if (! $line) {
            return ',';
        }

        $delimiters = [',', ';', "\t"];
        $counts = [];

        foreach ($delimiters as $del) {
            $counts[$del] = substr_count($line, $del);
        }

        arsort($counts);
        $best = key($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    private function mapHeaders(array $rawHeaders, array $specs): array
    {
        $map = [];
        $normalizedRaw = [];

        foreach ($rawHeaders as $idx => $header) {
            $normalizedRaw[$idx] = $this->normalizeColumnName($header);
        }

        foreach ($specs as $field => $spec) {
            $candidates = [$this->normalizeColumnName($field)];

            if (! empty($spec['label'])) {
                $candidates[] = $this->normalizeColumnName($spec['label']);
            }

            if (! empty($spec['aliases'])) {
                foreach ($spec['aliases'] as $alias) {
                    $candidates[] = $this->normalizeColumnName($alias);
                }
            }

            foreach ($normalizedRaw as $idx => $rawName) {
                if (in_array($rawName, $candidates, true)) {
                    $map[$field] = $idx;
                    break;
                }
            }
        }

        return $map;
    }

    private function normalizeColumnName(string $name): string
    {
        $name = preg_replace('/[^\p{L}\p{N}]+/u', '_', $name);

        return trim(mb_strtolower((string) $name, 'UTF-8'), '_');
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function readSheet(string $path, string $extension): ?array
    {
        if ($extension === 'xlsx' || $extension === 'xls') {
            try {
                $reader = $extension === 'xlsx'
                    ? new Xlsx
                    : new Xls;

                $spreadsheet = $reader->load($path);
                $worksheet = $spreadsheet->getActiveSheet();

                $maxCol = $worksheet->getHighestColumn();
                $maxRow = $worksheet->getHighestRow();

                if ($maxRow < 1) {
                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet);

                    return null;
                }

                $range = "A1:{$maxCol}{$maxRow}";
                $allRows = $worksheet->rangeToArray($range, null, true, true, false);

                if (empty($allRows)) {
                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet);

                    return null;
                }

                $rawHeaders = array_shift($allRows);

                while (! empty($rawHeaders) && (end($rawHeaders) === null || trim((string) end($rawHeaders)) === '')) {
                    array_pop($rawHeaders);
                }

                if (empty($rawHeaders)) {
                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet);

                    return null;
                }

                $headers = array_map(fn($h) => trim((string) $h), $rawHeaders);

                $dataGenerator = function () use ($allRows, $spreadsheet) {
                    try {
                        foreach ($allRows as $row) {
                            yield $row;
                        }
                    } finally {
                        $spreadsheet->disconnectWorksheets();
                        unset($spreadsheet);
                    }
                };

                return [
                    'headers' => $headers,
                    'rows' => $dataGenerator(),
                ];
            } catch (\Throwable) {
                return null;
            }
        }

        // CSV parsing
        $delimiter = $this->detectDelimiter($path);
        $handle = fopen($path, 'r');
        if (! $handle) {
            return null;
        }

        $rawHeaders = fgetcsv($handle, 0, $delimiter, '"', '\\');
        if ($rawHeaders === false || empty($rawHeaders)) {
            fclose($handle);

            return null;
        }

        // Strip UTF-8 BOM from first header column if present
        if (isset($rawHeaders[0])) {
            $rawHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $rawHeaders[0]);
        }

        while (! empty($rawHeaders) && (end($rawHeaders) === null || trim((string) end($rawHeaders)) === '')) {
            array_pop($rawHeaders);
        }

        if (empty($rawHeaders)) {
            fclose($handle);

            return null;
        }

        $headers = array_map(fn($h) => trim((string) $h), $rawHeaders);

        $rowGenerator = function () use ($handle, $delimiter) {
            try {
                while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                    yield $row;
                }
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        };

        return [
            'headers' => $headers,
            'rows' => $rowGenerator(),
        ];
    }
}
