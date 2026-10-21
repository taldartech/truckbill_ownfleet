<?php

namespace App\Services\TripExpenseImport;

use App\Enums\TripExpenseType;
use App\Support\VehicleRegistrationNumber;
use App\Trip;
use App\TripExpense;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FuelStatementImporter extends AbstractStatementImporter
{
    protected function importType(): string
    {
        return StatementColumnMapper::TYPE_FUEL;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    protected function shouldSkipMappedRow(array $mapped): ?string
    {
        if (array_key_exists('product', $mapped) && $mapped['product'] !== null && $mapped['product'] !== '') {
            $product = strtoupper((string) $mapped['product']);
            if (! preg_match('/DIESEL|HSD/', $product)) {
                return 'non_diesel';
            }
        }

        if (array_key_exists('credit_debit', $mapped) && $mapped['credit_debit'] !== null && $mapped['credit_debit'] !== '') {
            $cd = strtoupper(trim((string) $mapped['credit_debit']));
            if (preg_match('/\b(CREDIT|CR)\b/', $cd) && ! preg_match('/\b(DEBIT|DR)\b/', $cd)) {
                return 'credit';
            }
            if (! preg_match('/\b(DEBIT|DR)\b/', $cd) && preg_match('/\bC\b/', $cd) && ! preg_match('/\bD\b/', $cd)) {
                return 'credit';
            }
        }

        if (array_key_exists('txn_type', $mapped) && $mapped['txn_type'] !== null && $mapped['txn_type'] !== '') {
            $type = strtoupper((string) $mapped['txn_type']);
            if (preg_match('/RECHARGE|LOYALTY|TDS|CREDIT|REFUND|CASHBACK/', $type)) {
                return 'non_sale';
            }
        }

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
        $vrnRaw = trim((string) ($mapped['vrn'] ?? ''));
        $hasVrn = $vrnRaw !== '';
        $vehicle = $hasVrn
            ? $this->tripMatcher->resolveVehicle($tenantId, $vrnRaw)
            : ['vehicle_id' => null, 'vehicle_label' => null, 'issue' => 'No vehicle on statement — choose a trip'];

        $pump = trim((string) ($mapped['pump'] ?? ''));
        $volume = $this->parseVolume($mapped['volume'] ?? null);
        $rate = $this->parseAmount($mapped['rate'] ?? null);
        $amount = $this->parseAmount($mapped['amount'] ?? null);

        if ($volume && $volume > 0) {
            if (($amount === null || $amount <= 0) && $rate !== null && $rate > 0) {
                $amount = round($volume * $rate, 2);
            }
            if (($rate === null || $rate <= 0) && $amount !== null && $amount > 0) {
                $rate = round($amount / $volume, 2);
            }
        }

        $reference = trim((string) ($mapped['reference'] ?? ''));
        $issue = $vehicle['issue'];
        $trip = null;

        if ($vehicle['vehicle_id']) {
            $trip = $this->tripMatcher->matchTrip($vehicle['vehicle_id'], $transactedAt, $candidates);
            if (! $trip) {
                $issue = 'No trip found for this date & time';
            }
        }

        if ($pump === '') {
            $issue = $issue ?: 'Pump name is required';
        }

        if ($volume === null || $volume <= 0) {
            $issue = $issue ?: 'Volume must be greater than 0';
        }

        $include = $trip !== null
            && $issue === null
            && $volume !== null
            && $volume > 0
            && $amount !== null
            && $amount > 0
            && $rate !== null
            && $rate > 0;

        // Allow include when only issue is missing VRN / no trip — those need manual trip pick, so leave unticked.
        return [
            'row_number' => $rowNumber,
            'reference' => $reference !== '' ? $reference : null,
            'transacted_at' => $transactedAt->format('Y-m-d\TH:i'),
            'vrn' => $hasVrn ? (VehicleRegistrationNumber::normalize($vrnRaw) ?: null) : null,
            'truck_id' => $vehicle['vehicle_id'],
            'truck' => $vehicle['vehicle_label'],
            'plaza' => null,
            'pump_name' => $pump !== '' ? $pump : null,
            'liters' => $volume !== null ? number_format($volume, 3, '.', '') : null,
            'rate' => $rate !== null ? number_format($rate, 2, '.', '') : null,
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
            } else {
                $trip = Trip::query()->where('tenant_id', $tenantId)->whereKey($tripId)->first();
                if (! $trip) {
                    $errors["rows.$index.trip_id"] = 'Trip not found for this company.';
                } elseif ($this->tripIsCancelled($trip)) {
                    $errors["rows.$index.trip_id"] = 'Cancelled trips cannot receive expenses.';
                }
            }

            $liters = $this->parseVolume($row['liters'] ?? null);
            $rate = $this->parseAmount($row['rate'] ?? null);
            $amount = $this->parseAmount($row['amount'] ?? null);

            if ($liters === null || $liters <= 0) {
                $errors["rows.$index.liters"] = 'Liters must be greater than 0.';
            }
            if ($rate === null || $rate <= 0) {
                $errors["rows.$index.rate"] = 'Rate must be greater than 0.';
            }
            if ($amount === null || $amount <= 0) {
                $errors["rows.$index.amount"] = 'Amount must be greater than 0.';
            }

            $pump = trim((string) ($row['pump_name'] ?? ''));
            if ($pump === '') {
                $errors["rows.$index.pump_name"] = 'Pump name is required.';
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

        $liters = (float) $this->parseVolume($row['liters']);
        $rate = (float) $this->parseAmount($row['rate']);
        $amount = round($liters * $rate, 2);
        $pump = trim((string) ($row['pump_name'] ?? 'Fuel pump'));

        TripExpense::query()->create([
            'tenant_id' => $trip->tenant_id,
            'branch_id' => $trip->branch_id,
            'trip_id' => $trip->id,
            'expense_type' => TripExpenseType::Diesel->value,
            'expense_date' => $transactedAt->toDateString(),
            'amount' => $amount,
            'fuel_liters' => round($liters, 2),
            'fuel_rate' => round($rate, 2),
            'currency' => 'INR',
            'notes' => $this->formatNotes($pump, $transactedAt, $row['remark'] ?? null),
            'reference' => ($ref = trim((string) ($row['reference'] ?? ''))) !== '' ? $ref : null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}
