<?php

namespace App\Services\TripExpenseImport;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

class StatementDateParser
{
    public const TIMEZONE = 'Asia/Kolkata';

    /**
     * @return CarbonImmutable|null Datetime in Asia/Kolkata
     */
    public function parse(mixed $dateValue, mixed $timeValue = null): ?CarbonImmutable
    {
        $date = $this->parseDatePart($dateValue);

        if ($date === null) {
            return null;
        }

        $time = $this->parseTimePart($timeValue);

        if ($time !== null) {
            return $date->setTime($time['hour'], $time['minute'], $time['second']);
        }

        if ($this->valueIncludesTime($dateValue)) {
            return $date;
        }

        return $date->startOfDay();
    }

    public function parseCombined(mixed $value): ?CarbonImmutable
    {
        return $this->parse($value, null);
    }

    /**
     * @return array{hour: int, minute: int, second: int}|null
     */
    public function parseTimePart(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value) && ! is_string($value)) {
            $fraction = (float) $value;
            if ($fraction >= 0 && $fraction < 1) {
                $seconds = (int) round($fraction * 86400);
                $hour = intdiv($seconds, 3600);
                $minute = intdiv($seconds % 3600, 60);
                $second = $seconds % 60;

                return compact('hour', 'minute', 'second');
            }
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $formats = ['H:i:s', 'H:i', 'h:i:s A', 'h:i A', 'h:i:s a', 'h:i a', 'G:i:s', 'G:i'];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $raw, self::TIMEZONE);
                if ($parsed !== false) {
                    return [
                        'hour' => (int) $parsed->hour,
                        'minute' => (int) $parsed->minute,
                        'second' => (int) $parsed->second,
                    ];
                }
            } catch (\Throwable) {
                // try next
            }
        }

        return null;
    }

    private function parseDatePart(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 20000) {
            try {
                $excel = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value);

                return CarbonImmutable::instance($excel)->setTimezone(self::TIMEZONE);
            } catch (\Throwable) {
                // fall through
            }
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $formats = [
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y h:i:s A',
            'd/m/Y h:i A',
            'd/m/Y',
            'd-m-Y H:i:s',
            'd-m-Y H:i',
            'd-m-Y',
            'd-M-Y H:i:s',
            'd-M-Y H:i',
            'd-M-Y',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d',
            'd/m/y H:i:s',
            'd/m/y H:i',
            'd/m/y',
            'd-m-y',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $raw, self::TIMEZONE);
                if ($parsed !== false) {
                    return $parsed;
                }
            } catch (\Throwable) {
                // try next
            }
        }

        try {
            return CarbonImmutable::parse($raw, self::TIMEZONE);
        } catch (\Throwable) {
            return null;
        }
    }

    private function valueIncludesTime(mixed $value): bool
    {
        if (is_numeric($value) && (float) $value > 20000) {
            return abs((float) $value - floor((float) $value)) > 0.00001;
        }

        $raw = (string) $value;

        return (bool) preg_match('/\d{1,2}:\d{2}/', $raw);
    }
}
