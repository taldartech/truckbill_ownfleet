<?php

namespace App\Support;

class MaintenanceDueStatus
{
    public const DUE = 'due';

    public const UPCOMING = 'upcoming';

    public const NONE = 'none';

    /**
     * @return self::DUE|self::UPCOMING|self::NONE
     */
    public static function resolve(?float $odometerKm, ?float $nextDueKm, ?string $nextDueDate, ?string $today = null): string
    {
        $today ??= now()->toDateString();

        if ($nextDueKm === null && $nextDueDate === null) {
            return self::NONE;
        }

        $kmDue = $nextDueKm !== null && $odometerKm !== null && $odometerKm >= $nextDueKm;
        $dateDue = $nextDueDate !== null && $nextDueDate < $today;

        if ($kmDue || $dateDue) {
            return self::DUE;
        }

        return self::UPCOMING;
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::DUE => 'Due',
            self::UPCOMING => 'Upcoming',
            self::NONE => 'No Schedule',
            default => '—',
        };
    }

    /**
     * @return 'km'|'date'|'both'|null
     */
public static function overdueReason(
    ?float $odometerKm,
    ?float $nextDueKm,
    ?string $nextDueDate,
    ?string $today = null
): ?string {
    $today ??= now()->toDateString();

    $kmDue = $nextDueKm !== null
        && $odometerKm !== null
        && $odometerKm >= $nextDueKm;

    $dateDue = $nextDueDate !== null
        && $nextDueDate < $today;

    $hasKmSchedule = $nextDueKm !== null;
    $hasDateSchedule = $nextDueDate !== null;

    return match (true) {
        $hasKmSchedule && $hasDateSchedule && ($kmDue || $dateDue) => 'both',
        $hasKmSchedule && $kmDue => 'km',
        $hasDateSchedule && $dateDue => 'date',
        default => null,
    };
}

    public static function overdueReasonLabel(?string $reason): string
    {
        return match ($reason) {
            'both' => 'KM & Date',
            'km' => 'KM',
            'date' => 'Date',
            default => '—',
        };
    }

public static function scheduleFactor(
    ?float $nextDueKm,
    ?string $nextDueDate,
): ?string {
    $hasKm = $nextDueKm !== null;
    $hasDate = $nextDueDate !== null;

    return match (true) {
        $hasKm && $hasDate => 'both',
        $hasKm => 'km',
        $hasDate => 'date',
        default => null,
    };
}

public static function scheduleFactorLabel(?string $factor): string
{
    return match ($factor) {
        'both' => 'KM & Date',
        'km' => 'KM',
        'date' => 'Date',
        default => '—',
    };
}
}
