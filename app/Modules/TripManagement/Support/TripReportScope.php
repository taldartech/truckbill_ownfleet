<?php

namespace App\Modules\TripManagement\Support;

use App\Enums\TripStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * Shared trip attribution rules for dashboard and reports:
 * - only Completed / Invoiced trips
 * - period membership uses the earlier of end_at and actual_arrival_at
 */
final class TripReportScope
{
    /**
     * @return list<string>
     */
    public static function reportableStatuses(): array
    {
        return [
            TripStatus::Completed->value,
            TripStatus::Invoiced->value,
        ];
    }

    public static function reportDateExpression(string $alias = 'trips'): string
    {
        $arrival = "{$alias}.actual_arrival_at";
        $end = "{$alias}.end_at";

        return "CASE
            WHEN {$arrival} IS NULL THEN {$end}
            WHEN {$end} IS NULL THEN {$arrival}
            WHEN {$arrival} <= {$end} THEN {$arrival}
            ELSE {$end}
        END";
    }

    /**
     * @param  Builder<\App\Trip>|QueryBuilder|Builder<\App\TripExpense>  $query
     * @return Builder<\App\Trip>|QueryBuilder|Builder<\App\TripExpense>
     */
    public static function whereReportableStatuses($query, string $statusColumn = 'status')
    {
        return $query->whereIn($statusColumn, self::reportableStatuses());
    }

    /**
     * @param  Builder<\App\Trip>|QueryBuilder|Builder<\App\TripExpense>  $query
     * @return Builder<\App\Trip>|QueryBuilder|Builder<\App\TripExpense>
     */
    public static function whereReportDateInRange(
        $query,
        string|\DateTimeInterface $fromDate,
        string|\DateTimeInterface $toDate,
        string $alias = 'trips',
    ) {
        $from = Carbon::parse($fromDate)->toDateString();
        $to = Carbon::parse($toDate)->toDateString();
        $expression = self::reportDateExpression($alias);

        return $query
            ->whereRaw("({$expression}) IS NOT NULL")
            ->whereRaw("DATE({$expression}) >= ?", [$from])
            ->whereRaw("DATE({$expression}) <= ?", [$to]);
    }

    /**
     * @param  Builder<\App\Trip>|QueryBuilder|Builder<\App\TripExpense>  $query
     * @return Builder<\App\Trip>|QueryBuilder|Builder<\App\TripExpense>
     */
    public static function whereReportDateInMonth(
        $query,
        string $month,
        string $alias = 'trips',
    ) {
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        return self::whereReportDateInRange($query, $monthStart, $monthEnd, $alias);
    }
}
