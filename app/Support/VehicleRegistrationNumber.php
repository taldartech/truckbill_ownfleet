<?php

namespace App\Support;

class VehicleRegistrationNumber
{
    public const PATTERN = '/^(?:[A-Z]{2}[0-9]{1,2}[A-Z]{1,3}[0-9]{4}|[0-9]{2}BH[0-9]{4}[A-Z]{1,2})$/';

    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtoupper(trim($value));
        $normalized = preg_replace('/[^A-Z0-9]/', '', $normalized);

        return $normalized ?? '';
    }

    public static function isValid(?string $value): bool
    {
        $normalized = self::normalize($value);

        if ($normalized === null || $normalized === '') {
            return false;
        }

        return (bool) preg_match(self::PATTERN, $normalized);
    }
}
