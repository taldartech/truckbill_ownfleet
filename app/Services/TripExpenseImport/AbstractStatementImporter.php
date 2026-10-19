<?php

namespace App\Services\TripExpenseImport;

use App\Support\CurrentTenant;
use App\Trip;
use App\TripExpense;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

abstract class AbstractStatementImporter
{
    public function __construct(
        protected StatementSheetReader $reader,
        protected StatementColumnMapper $mapper,
        protected StatementDateParser $dateParser,
        protected TripExpenseImportTripMatcher $tripMatcher,
    ) {}

    abstract protected function importType(): string;

    /**
     * @return array<string, mixed>
     */
    public function analyze(UploadedFile $file, ?int $headerRow = null): array
    {
        $sheet = $this->reader->read($file);
        $detection = $this->mapper->detectHeaderRow($sheet['rows']);
        $headerRowNumber = $headerRow && $headerRow > 0 ? $headerRow : $detection['header_row'];
        $headerIndex = max(0, $headerRowNumber - 1);
        $headerCells = $sheet['rows'][$headerIndex] ?? [];

        $analyzed = $this->mapper->analyzeHeaders($this->importType(), $headerCells);

        return [
            'import_type' => $this->importType(),
            'header_row' => $headerRowNumber,
            'columns' => $analyzed['columns'],
            'suggested' => $analyzed['suggested'],
            'fields' => $this->mapper->fieldsFor($this->importType()),
            'header_candidates' => $detection['candidates'],
        ];
    }

    /**
     * @param  array<string, int|string|null>  $columnMap
     * @return array<string, mixed>
     */
    public function preview(UploadedFile $file, string $statementDate, array $columnMap, ?int $headerRow = null): array
    {
        $errors = $this->mapper->validateMap($this->importType(), $columnMap);
        if ($errors !== []) {
            throw ValidationException::withMessages(['column_map' => $errors]);
        }

        $statementDateIst = CarbonImmutable::createFromFormat('Y-m-d', $statementDate, StatementDateParser::TIMEZONE)
            ?->startOfDay();

        if (! $statementDateIst) {
            throw ValidationException::withMessages(['statement_date' => ['Invalid statement date.']]);
        }

        $tenantId = (string) CurrentTenant::id();
        $sheet = $this->reader->read($file);
        $headerRowNumber = $headerRow && $headerRow > 0 ? $headerRow : $this->mapper->detectHeaderRow($sheet['rows'])['header_row'];
        $headerIndex = max(0, $headerRowNumber - 1);

        $candidates = $this->tripMatcher->candidateTrips($tenantId, $statementDateIst);
        $parsedRows = [];
        $otherDates = 0;
        $skipped = 0;
        $references = [];

        for ($i = $headerIndex + 1; $i < count($sheet['rows']); $i++) {
            $row = $sheet['rows'][$i] ?? [];
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $mapped = $this->extractMappedValues($row, $columnMap);
            $filter = $this->shouldSkipMappedRow($mapped);
            if ($filter !== null) {
                $skipped++;

                continue;
            }

            $transactedAt = $this->resolveTransactedAt($mapped);
            if ($transactedAt === null) {
                $skipped++;

                continue;
            }

            if ($transactedAt->toDateString() !== $statementDateIst->toDateString()) {
                $otherDates++;

                continue;
            }

            if (count($parsedRows) >= StatementSheetReader::MAX_ROWS) {
                break;
            }

            $previewRow = $this->buildPreviewRow($tenantId, $i + 1, $mapped, $transactedAt, $candidates);
            $parsedRows[] = $previewRow;
            if (! empty($previewRow['reference'])) {
                $references[] = $previewRow['reference'];
            }
        }

        $existing = $this->tripMatcher->existingReferences($tenantId, $references);

        foreach ($parsedRows as &$row) {
            $ref = (string) ($row['reference'] ?? '');
            if ($ref !== '' && isset($existing[$ref])) {
                $row['already_imported'] = true;
                $row['issue'] = $row['issue'] ?: 'Already imported';
                $row['include'] = false;
            }
        }
        unset($row);

        return [
            'import_type' => $this->importType(),
            'rows' => $parsedRows,
            'trips' => $this->tripMatcher->tripOptions($candidates),
            'skipped' => $skipped,
            'other_dates' => $otherDates,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, skipped: int, failed: int, message: string}
     */
    public function store(array $rows, string $userId): array
    {
        $tenantId = (string) CurrentTenant::id();
        $selected = array_values(array_filter($rows, static fn ($row) => ! empty($row['include'])));

        if ($selected === []) {
            throw ValidationException::withMessages(['rows' => ['Select at least one row to import.']]);
        }

        $this->validateStoreRows($selected, $tenantId);

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $seenRefs = [];
        $touchedTripIds = [];

        DB::transaction(function () use ($selected, $tenantId, $userId, &$created, &$skipped, &$failed, &$seenRefs, &$touchedTripIds) {
            foreach ($selected as $row) {
                $reference = trim((string) ($row['reference'] ?? ''));
                if ($reference !== '') {
                    if (isset($seenRefs[$reference])) {
                        $skipped++;

                        continue;
                    }
                    $seenRefs[$reference] = true;

                    $exists = TripExpense::query()
                        ->where('tenant_id', $tenantId)
                        ->where('reference', $reference)
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }
                }

                try {
                    $tripId = (string) ($row['trip_id'] ?? '');
                    $trip = Trip::query()
                        ->where('tenant_id', $tenantId)
                        ->whereKey($tripId)
                        ->first();

                    if (! $trip || $this->tripIsCancelled($trip)) {
                        $failed++;

                        continue;
                    }

                    $this->createExpense($trip, $row, $userId);
                    $touchedTripIds[$trip->id] = true;
                    $created++;
                } catch (\Throwable) {
                    $failed++;
                }
            }

            foreach (array_keys($touchedTripIds) as $tripId) {
                $this->recalculateTripExpenseTotal((string) $tripId);
            }
        });

        return [
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
            'message' => sprintf('Imported %d expense(s). Skipped %d. Failed %d.', $created, $skipped, $failed),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    abstract protected function validateStoreRows(array $rows, string $tenantId): void;

    /**
     * @param  array<string, mixed>  $mapped
     */
    abstract protected function shouldSkipMappedRow(array $mapped): ?string;

    /**
     * @param  array<string, mixed>  $mapped
     * @param  \Illuminate\Support\Collection<int, Trip>  $candidates
     * @return array<string, mixed>
     */
    abstract protected function buildPreviewRow(
        string $tenantId,
        int $rowNumber,
        array $mapped,
        CarbonImmutable $transactedAt,
        $candidates
    ): array;

    /**
     * @param  array<string, mixed>  $row
     */
    abstract protected function createExpense(Trip $trip, array $row, string $userId): void;

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int|string|null>  $columnMap
     * @return array<string, mixed>
     */
    protected function extractMappedValues(array $row, array $columnMap): array
    {
        $mapped = [];
        foreach ($columnMap as $field => $index) {
            if ($index === null || $index === '' || $index === 'null') {
                $mapped[$field] = null;

                continue;
            }
            $idx = (int) $index;
            $mapped[$field] = $row[$idx] ?? null;
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    protected function resolveTransactedAt(array $mapped): ?CarbonImmutable
    {
        if (! empty($mapped['date_time'])) {
            return $this->dateParser->parseCombined($mapped['date_time']);
        }

        return $this->dateParser->parse($mapped['transaction_date'] ?? null, $mapped['transaction_time'] ?? null);
    }

    /**
     * @param  list<mixed>  $row
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && $cell !== '') {
                return false;
            }
        }

        return true;
    }

    protected function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);
        $raw = str_replace([',', '₹', ' '], '', $raw);
        $raw = rtrim($raw, '-');
        $raw = ltrim($raw, '+');

        if ($raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return abs((float) $raw);
    }

    protected function parseVolume(mixed $value): ?float
    {
        return $this->parseAmount($value);
    }

    protected function formatNotes(string $place, CarbonImmutable $transactedAt, ?string $remark = null): string
    {
        $stamp = $transactedAt->format('d/m/Y H:i');
        $notes = trim($place) !== '' ? trim($place).' at '.$stamp : 'Expense at '.$stamp;

        $remark = trim((string) $remark);
        if ($remark !== '') {
            $notes .= ' — '.$remark;
        }

        return $notes;
    }

    protected function recalculateTripExpenseTotal(string $tripId): void
    {
        $total = (float) TripExpense::query()
            ->where('trip_id', $tripId)
            ->sum('amount');

        Trip::query()->whereKey($tripId)->update([
            'total_expense_amount' => round($total, 2),
        ]);
    }

    protected function tripIsCancelled(Trip $trip): bool
    {
        if ($trip->cancelled_at) {
            return true;
        }

        $status = $trip->status instanceof \App\Enums\TripStatus
            ? $trip->status
            : \App\Enums\TripStatus::tryFrom((string) $trip->status);

        return $status === \App\Enums\TripStatus::Cancelled;
    }
}
