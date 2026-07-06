<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class VehicleDocumentStatus
{
    /**
     * @return array{
     *     status_tier: string,
     *     status_label: string,
     *     status_sublabel: string|null,
     *     days_remaining: int|null,
     *     badge_color: string,
     *     row_color: string,
     * }
     */
    public static function compute(
        ?Carbon $expiryDate,
        ?int $reminderDaysBefore = 7,
        ?Carbon $today = null,
    ): array {
        $today = ($today ?? Carbon::today())->copy()->startOfDay();
        $reminderDays = max(1, $reminderDaysBefore ?? 7);

        if ($expiryDate === null) {
            return [
                'status_tier' => 'valid',
                'status_label' => 'Valid',
                'status_sublabel' => null,
                'days_remaining' => null,
                'badge_color' => 'success',
                'row_color' => 'success',
            ];
        }

        $expiry = $expiryDate->copy()->startOfDay();
        $daysRemaining = (int) $today->diffInDays($expiry, false);

        if ($daysRemaining < 0) {
            $overdue = abs($daysRemaining);

            return [
                'status_tier' => 'expired',
                'status_label' => $overdue === 1 ? '1 Day Overdue' : "{$overdue} Days Overdue",
                'status_sublabel' => "{$overdue}d",
                'days_remaining' => $daysRemaining,
                'badge_color' => 'danger',
                'row_color' => 'danger',
            ];
        }

        if ($daysRemaining === 0) {
            return [
                'status_tier' => 'today',
                'status_label' => 'Expires Today',
                'status_sublabel' => 'Today',
                'days_remaining' => 0,
                'badge_color' => 'warning',
                'row_color' => 'warning',
            ];
        }

        if ($daysRemaining <= $reminderDays) {
            $label = $daysRemaining === 1 ? '1 Day Left' : "{$daysRemaining} Days Left";

            return [
                'status_tier' => 'reminder',
                'status_label' => $label,
                'status_sublabel' => "{$daysRemaining}d",
                'days_remaining' => $daysRemaining,
                'badge_color' => 'warning',
                'row_color' => 'warning',
            ];
        }

        return [
            'status_tier' => 'valid',
            'status_label' => 'Valid',
            'status_sublabel' => "{$daysRemaining}d",
            'days_remaining' => $daysRemaining,
            'badge_color' => 'success',
            'row_color' => 'success',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function sortByUrgency(array $items): array
    {
        usort($items, function (array $left, array $right): int {
            $leftDays = $left['days_remaining'] ?? PHP_INT_MAX;
            $rightDays = $right['days_remaining'] ?? PHP_INT_MAX;

            return $leftDays <=> $rightDays;
        });

        return $items;
    }
}
