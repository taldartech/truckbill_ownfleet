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

        $from = Carbon::parse($filters['from'])->startOfDay();
        $to = Carbon::parse($filters['to'])->startOfDay();
        $today = Carbon::today();

        if ($from->gt($to)) {
            throw ValidationException::withMessages([
                'to' => 'End date must be on or after start date.',
            ]);
        }

        if ($from->gt($today) || $to->gt($today)) {
            throw ValidationException::withMessages([
                'to' => 'Future dates are not allowed.',
            ]);
        }

        $earliestAllowed = $to->copy()->subMonths(self::MAX_MONTHS);

        if ($from->lt($earliestAllowed)) {
            throw ValidationException::withMessages([
                'from' => 'Maximum allowed date range is '.self::MAX_MONTHS.' months.',
            ]);
        }
    }
}
