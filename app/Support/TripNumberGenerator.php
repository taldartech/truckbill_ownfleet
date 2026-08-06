<?php

namespace App\Support;

use App\BranchSetting;
use App\Invoice;
use App\Trip;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripNumberGenerator
{
    public const DOCUMENT_TRIP = 'trip';

    public const DOCUMENT_INVOICE = 'invoice';

    /**
     * @return array{number: string, sequence: int}
     */
    public function preview(
        string $documentType,
        ?string $tenantId,
        ?int $branchId,
        CarbonInterface|string|null $documentDate = null,
        ?int $sequence = null,
    ): array {
        if ($tenantId === null || $branchId === null) {
            $settings = $this->fallbackSettings($documentType);
            $date = $this->resolveDate($documentDate);
            $resolvedSequence = max(1, $sequence ?? 1);

            return [
                'number' => $this->formatNumber($settings, $documentType, $date, $resolvedSequence),
                'sequence' => $resolvedSequence,
            ];
        }

        $settings = $this->settingsForBranch($branchId);
        $date = $this->resolveDate($documentDate);
        $resolvedSequence = $sequence ?? $this->nextSequence(
            $documentType,
            $tenantId,
            $branchId,
            $settings,
            $date,
        );

        return [
            'number' => $this->formatNumber($settings, $documentType, $date, $resolvedSequence),
            'sequence' => $resolvedSequence,
        ];
    }

    /**
     * @return array{number: string, sequence: int}
     */
    public function allocate(
        string $documentType,
        string $tenantId,
        int $branchId,
        CarbonInterface|string|null $documentDate = null,
        ?int $sequence = null,
    ): array {
        return DB::transaction(function () use ($documentType, $tenantId, $branchId, $documentDate, $sequence): array {
            $settings = BranchSetting::query()
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->first();

            if ($settings === null) {
                $settings = BranchSetting::createForBranch($branchId);
                $settings = BranchSetting::query()
                    ->whereKey($settings->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $date = $this->resolveDate($documentDate);
            $resolvedSequence = $sequence ?? $this->nextSequence(
                $documentType,
                $tenantId,
                $branchId,
                $settings,
                $date,
                lock: true,
            );

            if ($resolvedSequence < 1) {
                throw ValidationException::withMessages([
                    $this->sequenceField($documentType) => ['Sequence must be at least 1.'],
                ]);
            }

            $number = $this->formatNumber($settings, $documentType, $date, $resolvedSequence);

            if ($this->numberExists($documentType, $tenantId, $branchId, $number, lock: true)) {
                throw ValidationException::withMessages([
                    $this->sequenceField($documentType) => [
                        $documentType === self::DOCUMENT_INVOICE
                            ? 'This invoice sequence is already used for the current number format.'
                            : 'This trip sequence is already used for the current number format.',
                    ],
                ]);
            }

            return [
                'number' => $number,
                'sequence' => $resolvedSequence,
            ];
        });
    }

    public function generate(?string $tenantId, ?int $branchId, CarbonInterface|string|null $startAt = null, ?int $sequence = null): string
    {
        if ($tenantId === null) {
            throw new \InvalidArgumentException('Tenant context is required to generate a trip number.');
        }

        if ($branchId === null) {
            throw new \InvalidArgumentException('Branch context is required to generate a trip number.');
        }

        return $this->allocate(self::DOCUMENT_TRIP, $tenantId, $branchId, $startAt, $sequence)['number'];
    }

    public function formatExample(BranchSetting $settings, string $documentType, ?CarbonInterface $date = null, int $sequence = 1): string
    {
        return $this->formatNumber($settings, $documentType, $date ?? now(), $sequence);
    }

    private function settingsForBranch(int $branchId): BranchSetting
    {
        $settings = BranchSetting::query()->where('branch_id', $branchId)->first();

        if ($settings !== null) {
            return $settings;
        }

        return BranchSetting::createForBranch($branchId);
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackSettings(string $documentType): array
    {
        $defaults = BranchSetting::defaultsForBranch(0);

        return $documentType === self::DOCUMENT_INVOICE
            ? [
                'initial' => 'INV-BR000',
                'separator_1' => $defaults['invoice_separator_1'],
                'year_type' => $defaults['invoice_year_type'],
                'year_digits' => $defaults['invoice_year_digits'],
                'separator_2' => $defaults['invoice_separator_2'],
                'sequence_digits' => $defaults['invoice_sequence_digits'],
            ]
            : [
                'initial' => 'TRIP-BR000',
                'separator_1' => $defaults['trip_separator_1'],
                'year_type' => $defaults['trip_year_type'],
                'year_digits' => $defaults['trip_year_digits'],
                'separator_2' => $defaults['trip_separator_2'],
                'sequence_digits' => $defaults['trip_sequence_digits'],
            ];
    }

    /**
     * @return array{initial: string, separator_1: string, year_type: string, year_digits: int, separator_2: string, sequence_digits: int}
     */
    private function formatConfig(BranchSetting|array $settings, string $documentType): array
    {
        if (is_array($settings)) {
            return [
                'initial' => (string) $settings['initial'],
                'separator_1' => (string) $settings['separator_1'],
                'year_type' => (string) $settings['year_type'],
                'year_digits' => (int) $settings['year_digits'],
                'separator_2' => (string) $settings['separator_2'],
                'sequence_digits' => (int) $settings['sequence_digits'],
            ];
        }

        if ($documentType === self::DOCUMENT_INVOICE) {
            return [
                'initial' => (string) $settings->invoice_initial,
                'separator_1' => (string) $settings->invoice_separator_1,
                'year_type' => (string) $settings->invoice_year_type,
                'year_digits' => (int) $settings->invoice_year_digits,
                'separator_2' => (string) $settings->invoice_separator_2,
                'sequence_digits' => (int) $settings->invoice_sequence_digits,
            ];
        }

        return [
            'initial' => (string) $settings->trip_initial,
            'separator_1' => (string) $settings->trip_separator_1,
            'year_type' => (string) $settings->trip_year_type,
            'year_digits' => (int) $settings->trip_year_digits,
            'separator_2' => (string) $settings->trip_separator_2,
            'sequence_digits' => (int) $settings->trip_sequence_digits,
        ];
    }

    private function formatNumber(
        BranchSetting|array $settings,
        string $documentType,
        CarbonInterface $date,
        int $sequence,
    ): string {
        $config = $this->formatConfig($settings, $documentType);
        $datePart = $this->datePart($config['year_type'], $config['year_digits'], $date);
        $paddedSequence = $this->formatSequence($sequence, $config['sequence_digits']);

        if ($datePart === '') {
            return $config['initial'].$config['separator_1'].$paddedSequence;
        }

        return $config['initial']
            .$config['separator_1']
            .$datePart
            .$config['separator_2']
            .$paddedSequence;
    }

    private function datePart(string $yearType, int $yearDigits, CarbonInterface $date): string
    {
        $digits = $yearDigits === 2 ? 2 : 4;

        return match ($yearType) {
            BranchSetting::YEAR_TYPE_BLANK => '',
            BranchSetting::YEAR_TYPE_FINANCIAL_YEAR => $this->financialYearPart($date, $digits),
            default => $digits === 2 ? $date->format('y') : $date->format('Y'),
        };
    }

    private function financialYearPart(CarbonInterface $date, int $digits): string
    {
        $startYear = $date->month >= 4 ? $date->year : $date->year - 1;
        $endYear = $startYear + 1;

        if ($digits === 2) {
            return sprintf('%02d-%02d', $startYear % 100, $endYear % 100);
        }

        return sprintf('%d-%02d', $startYear, $endYear % 100);
    }

    private function formatSequence(int $sequence, int $minimumDigits): string
    {
        $width = max($minimumDigits, strlen((string) $sequence));

        return str_pad((string) $sequence, $width, '0', STR_PAD_LEFT);
    }

    private function nextSequence(
        string $documentType,
        string $tenantId,
        int $branchId,
        BranchSetting $settings,
        CarbonInterface $date,
        bool $lock = false,
    ): int {
        $prefix = $this->numberPrefix($settings, $documentType, $date);
        $column = $documentType === self::DOCUMENT_INVOICE ? 'invoice_number' : 'trip_number';

        $query = $this->documentQuery($documentType)
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where($column, 'like', $prefix.'%');

        $this->applyDocumentVisibility($query, $documentType);

        if ($lock) {
            $query->lockForUpdate();
        }

        $numbers = $query->pluck($column);
        $maxSequence = 0;

        foreach ($numbers as $number) {
            $suffix = substr((string) $number, strlen($prefix));

            if ($suffix !== '' && ctype_digit($suffix)) {
                $maxSequence = max($maxSequence, (int) $suffix);
            }
        }

        return $maxSequence + 1;
    }

    private function numberPrefix(
        BranchSetting|array $settings,
        string $documentType,
        CarbonInterface $date,
    ): string {
        $config = $this->formatConfig($settings, $documentType);
        $datePart = $this->datePart($config['year_type'], $config['year_digits'], $date);

        if ($datePart === '') {
            return $config['initial'].$config['separator_1'];
        }

        return $config['initial'].$config['separator_1'].$datePart.$config['separator_2'];
    }

    private function numberExists(
        string $documentType,
        string $tenantId,
        int $branchId,
        string $number,
        bool $lock = false,
    ): bool {
        $column = $documentType === self::DOCUMENT_INVOICE ? 'invoice_number' : 'trip_number';

        $query = $this->documentQuery($documentType)
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('branch_id', $branchId)
            ->where($column, $number);

        $this->applyDocumentVisibility($query, $documentType);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }

    private function applyDocumentVisibility(Builder $query, string $documentType): void
    {
        if ($documentType === self::DOCUMENT_INVOICE) {
            // Soft-deleted invoices free their numbers for reuse.
            $query->whereNull($query->getModel()->getQualifiedDeletedAtColumn());

            return;
        }

        // Archived trips keep their numbers reserved.
        $query->withTrashed();
    }

    private function documentQuery(string $documentType): Builder
    {
        return $documentType === self::DOCUMENT_INVOICE
            ? Invoice::query()
            : Trip::query();
    }

    private function resolveDate(CarbonInterface|string|null $documentDate): CarbonInterface
    {
        if ($documentDate instanceof CarbonInterface) {
            return $documentDate->copy();
        }

        if (is_string($documentDate) && trim($documentDate) !== '') {
            return Carbon::parse($documentDate);
        }

        return now();
    }

    private function sequenceField(string $documentType): string
    {
        return $documentType === self::DOCUMENT_INVOICE ? 'invoice_sequence' : 'trip_sequence';
    }
}
