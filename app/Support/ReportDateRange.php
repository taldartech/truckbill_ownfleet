<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ReportDateRange
{
    public const MAX_MONTHS = 3;

    /**
     * @param  array{from?: string|null, to?: string|null}  $filters
     */
    public static function assertValid(array $filters): void
    {
        if (empty($filters['from']) || empty($filters['to'])) {
            throw ValidationException::withMessages([
                'from' => 'Date range is required.',
                'to' => 'Date range is required.',
            ]);
        }

        self::assertValidDates(
            (string) $filters['from'],
            (string) $filters['to'],
            'from',
            'to',
        );
    }

    public static function assertValidDates(
        string $fromDate,
        string $toDate,
        string $fromField = 'from',
        string $toField = 'to',
    ): void {
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->startOfDay();
        $today = Carbon::today();

        if ($from->gt($today)) {
            throw ValidationException::withMessages([
                $fromField => 'Future date is not allowed.',
            ]);
        }

        if ($to->gt($today)) {
            throw ValidationException::withMessages([
                $toField => 'Future date is not allowed.',
            ]);
        }

        if ($from->gt($to)) {
            throw ValidationException::withMessages([
                $toField => 'To Date cannot be earlier than From Date.',
            ]);
        }

        $earliestAllowed = $to->copy()->subMonths(self::MAX_MONTHS);

        if ($from->lt($earliestAllowed)) {
            throw ValidationException::withMessages([
                $fromField => 'Maximum allowed date range is '.self::MAX_MONTHS.' months.',
            ]);
        }
    }
}
