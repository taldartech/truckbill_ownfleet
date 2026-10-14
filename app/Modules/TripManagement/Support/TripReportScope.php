<?php

namespace App\Modules\TripManagement\Support;

use App\Enums\TripStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * Shared trip attribution rules for dashboard and reports:
 * - excludes Cancelled trips
 * - period membership uses the trip start date (start_at)
 */
final class TripReportScope
{
    /**
     * @return list<string>
     */
    public static function reportableStatuses(): array
    {
        return [
            TripStatus::Pending->value,
            TripStatus::InProgress->value,
            TripStatus::Completed->value,
            TripStatus::Invoiced->value,
        ];
    }

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
        $column = "{$alias}.start_at";

        return $query
            ->whereNotNull($column)
            ->whereRaw("DATE({$column}) >= ?", [$from])
            ->whereRaw("DATE({$column}) <= ?", [$to]);
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
