<?php

namespace App\Support;

use App\Enums\TripExpenseType;
use App\Enums\TripStatus;
use App\Trip;
use App\TripExpense;
use App\User;

class TripArchiveImpact
{
    /**
     * @return list<TripStatus>
     */
    public static function archivableStatuses(): array
    {
        return [
            TripStatus::Pending,
            TripStatus::InProgress,
            TripStatus::Cancelled,
        ];
    }

    public static function isArchivable(?TripStatus $status, ?User $user = null): bool
    {
        if ($status === null) {
            return false;
        }

        return in_array($status, self::archivableStatuses(), true);
    }

    /**
     * @return array<string, mixed>
     */
    public static function forTrip(Trip $trip, ?User $user = null): array
    {
        $trip->loadMissing(['vehicle', 'driver', 'routeLegs', 'expenses']);

        $status = $trip->status instanceof TripStatus
            ? $trip->status
            : TripStatus::tryFrom((string) $trip->status);

        $routeStopsCount = $trip->routeLegs->count();
        $expensesCount = $trip->expenses->count();
        $totalExpenseAmount = (float) ($trip->total_expense_amount ?? 0);

        if ($totalExpenseAmount <= 0 && $expensesCount > 0) {
            $totalExpenseAmount = (float) $trip->expenses->sum('amount');
        }

        $canArchive = self::isArchivable($status, $user);

        return [
            'trip' => [
                'id' => $trip->id,
                'trip_number' => $trip->trip_number,
                'truck_registration' => $trip->vehicle?->registration_number,
                'driver_name' => $trip->driver?->name,
                'status' => $status?->value ?? (string) $trip->status,
                'status_label' => self::statusLabel($status),
                'start_at' => ApiDateTime::toIso($trip->start_at),
                'end_at' => ApiDateTime::toIso($trip->end_at),
            ],
            'linked_records' => [
                'route_stops_count' => $routeStopsCount,
                'expenses_count' => $expensesCount,
                'total_expense_amount' => round($totalExpenseAmount, 2),
                'expenses_by_type' => self::expensesByType($trip),
            ],
            'warnings' => [
                'has_financial_records' => $totalExpenseAmount > 0,
            ],
            'can_archive' => $canArchive,
            'block_reason' => $canArchive
                ? null
                : (in_array($status, [TripStatus::Completed, TripStatus::Invoiced], true)
                    ? 'Completed or invoiced trips cannot be archived.'
                    : 'This trip cannot be archived or deleted.'),
        ];
    }

    private static function statusLabel(?TripStatus $status): string
    {
        return match ($status) {
            TripStatus::Pending => 'Pending',
            TripStatus::Cancelled => 'Cancelled',
            TripStatus::InProgress => 'Running',
            TripStatus::Completed => 'Completed',
            TripStatus::Invoiced => 'Invoiced',
            default => 'Unknown',
        };
    }

    /**
     * @return list<array{type: string, label: string, count: int}>
     */
    private static function expensesByType(Trip $trip): array
    {
        $grouped = $trip->expenses
            ->groupBy(fn (TripExpense $expense): string => $expense->expense_type instanceof TripExpenseType
                ? $expense->expense_type->value
                : (string) $expense->expense_type);

        $dieselCount = (int) ($grouped->get(TripExpenseType::Diesel->value)?->count() ?? 0);
        $tollCount = (int) ($grouped->get(TripExpenseType::Toll->value)?->count() ?? 0);

        $otherCount = $trip->expenses->count() - $dieselCount - $tollCount;

        $lines = [];

        if ($dieselCount > 0) {
            $lines[] = [
                'type' => TripExpenseType::Diesel->value,
                'label' => 'Fuel Entries',
                'count' => $dieselCount,
            ];
        }

        if ($tollCount > 0) {
            $lines[] = [
                'type' => TripExpenseType::Toll->value,
                'label' => 'Toll Entries',
                'count' => $tollCount,
            ];
        }

        if ($otherCount > 0) {
            $lines[] = [
                'type' => 'OTHER',
                'label' => 'Other Expense',
                'count' => $otherCount,
            ];
        }

        return $lines;
    }
}

/** @deprecated Use TripArchiveImpact */
class TripDeletionImpact extends TripArchiveImpact
{
    public static function forTrip(Trip $trip, ?User $user = null): array
    {
        $impact = parent::forTrip($trip, $user);
        $impact['can_delete'] = $impact['can_archive'];

        return $impact;
    }
}
