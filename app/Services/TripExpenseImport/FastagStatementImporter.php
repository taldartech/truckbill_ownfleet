<?php

namespace App\Services\TripExpenseImport;

use App\Enums\TripExpenseType;
use App\Support\VehicleRegistrationNumber;
use App\Trip;
use App\TripExpense;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FastagStatementImporter extends AbstractStatementImporter
{
    protected function importType(): string
    {
        return StatementColumnMapper::TYPE_FASTAG;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    protected function shouldSkipMappedRow(array $mapped): ?string
    {
        $type = strtoupper(trim((string) ($mapped['type'] ?? '')));
        if ($type === '') {
            return null;
        }

        if (preg_match('/\b(CREDIT|CR|RECHARGE|REFUND|LOYALTY)\b/', $type)) {
            return 'non_debit';
        }

        if (preg_match('/\b(DEBIT|DR|TOLL)\b/', $type)) {
            return null;
        }

        // If type column is mapped with an unrecognized non-debit-looking value, keep the row.
        return null;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  Collection<int, Trip>  $candidates
     * @return array<string, mixed>
     */
    protected function buildPreviewRow(
        string $tenantId,
        int $rowNumber,
        array $mapped,
        CarbonImmutable $transactedAt,
        $candidates
    ): array {
        $vrnRaw = (string) ($mapped['vrn'] ?? '');
        $vehicle = $this->tripMatcher->resolveVehicle($tenantId, $vrnRaw);
        $amount = $this->parseAmount($mapped['debit'] ?? null);
        $reference = trim((string) ($mapped['reference'] ?? ''));
        $plaza = trim((string) ($mapped['description'] ?? ''));

        $issue = $vehicle['issue'];
        $trip = null;

        if ($vehicle['vehicle_id']) {
            $trip = $this->tripMatcher->matchTrip($vehicle['vehicle_id'], $transactedAt, $candidates);
            if (! $trip) {
                $issue = 'No trip found for this date & time';
            }
        }

        $include = $issue === null && $trip !== null && $amount !== null && $amount > 0;

        return [
            'row_number' => $rowNumber,
            'reference' => $reference !== '' ? $reference : null,
            'transacted_at' => $transactedAt->format('Y-m-d\TH:i'),
            'vrn' => VehicleRegistrationNumber::normalize($vrnRaw) ?: null,
            'truck_id' => $vehicle['vehicle_id'],
            'truck' => $vehicle['vehicle_label'],
            'plaza' => $plaza !== '' ? $plaza : null,
            'pump_name' => null,
            'liters' => null,
            'rate' => null,
            'amount' => $amount !== null ? number_format($amount, 2, '.', '') : null,
            'trip_id' => $trip?->id,
            'remark' => '',
            'include' => $include,
            'already_imported' => false,
            'issue' => $issue,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    protected function validateStoreRows(array $rows, string $tenantId): void
    {
        $errors = [];

        foreach ($rows as $index => $row) {
            $tripId = (string) ($row['trip_id'] ?? '');
            if ($tripId === '') {
                $errors["rows.$index.trip_id"] = 'Trip is required.';

                continue;
            }

            $trip = Trip::query()->where('tenant_id', $tenantId)->whereKey($tripId)->first();
            if (! $trip) {
                $errors["rows.$index.trip_id"] = 'Trip not found for this company.';
            } elseif ($this->tripIsCancelled($trip)) {
                $errors["rows.$index.trip_id"] = 'Cancelled trips cannot receive expenses.';
            }

            $amount = $this->parseAmount($row['amount'] ?? null);
            if ($amount === null || $amount <= 0) {
                $errors["rows.$index.amount"] = 'Amount must be greater than 0.';
            }

        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function createExpense(Trip $trip, array $row, string $userId): void
    {
        $transactedAt = CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $row['transacted_at'], StatementDateParser::TIMEZONE)
            ?: CarbonImmutable::parse((string) $row['transacted_at'], StatementDateParser::TIMEZONE);

        $plaza = trim((string) ($row['plaza'] ?? ''));
        $place = $plaza !== '' ? $plaza : 'FASTag toll';

        TripExpense::query()->create([
            'tenant_id' => $trip->tenant_id,
            'branch_id' => $trip->branch_id,
            'trip_id' => $trip->id,
            'expense_type' => TripExpenseType::Toll->value,
            'expense_date' => $transactedAt->toDateString(),
            'amount' => round((float) $this->parseAmount($row['amount']), 2),
            'currency' => 'INR',
            'notes' => $this->formatNotes($place, $transactedAt, $row['remark'] ?? null),
            'reference' => ($ref = trim((string) ($row['reference'] ?? ''))) !== '' ? $ref : null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}
