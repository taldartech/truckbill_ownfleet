<?php

namespace App\Support;

use App\Enums\PayrollPayDaysMode;
use App\Tenant;

class PayrollPayDays
{
    public const FIXED_DAYS = 30;

    /** @var array<string, PayrollPayDaysMode> */
    private static array $modeCache = [];

    public static function clearCache(): void
    {
        self::$modeCache = [];
    }

    public static function modeForTenant(?string $tenantId): PayrollPayDaysMode
    {
        if ($tenantId === null) {
            return PayrollPayDaysMode::Calendar;
        }

        if (isset(self::$modeCache[$tenantId])) {
            return self::$modeCache[$tenantId];
        }

        $mode = Tenant::query()->whereKey($tenantId)->value('payroll_pay_days_mode');

        if ($mode instanceof PayrollPayDaysMode) {
            return self::$modeCache[$tenantId] = $mode;
        }

        return self::$modeCache[$tenantId] = PayrollPayDaysMode::tryFrom((string) $mode) ?? PayrollPayDaysMode::Calendar;
    }

    public static function modeForCurrentTenant(): PayrollPayDaysMode
    {
        return self::modeForTenant(CurrentTenant::id());
    }

    public static function divisor(int $calendarDays, PayrollPayDaysMode $mode): int
    {
        if ($mode === PayrollPayDaysMode::Fixed30) {
            return self::FIXED_DAYS;
        }

        return max(1, $calendarDays);
    }

    public static function divisorForDate(string $date, ?PayrollPayDaysMode $mode = null): int
    {
        $calendarDays = (int) \Illuminate\Support\Carbon::parse($date)->daysInMonth;
        $resolvedMode = $mode ?? self::modeForCurrentTenant();

        return self::divisor($calendarDays, $resolvedMode);
    }

    public static function displayPayDays(int $calendarDays, PayrollPayDaysMode $mode): int
    {
        return self::divisor($calendarDays, $mode);
    }
}
