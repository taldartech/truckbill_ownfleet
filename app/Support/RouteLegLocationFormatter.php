<?php

namespace App\Support;

class RouteLegLocationFormatter
{
    public static function compose(?string $city, ?string $address, ?string $fallback = null): string
    {
        $city = trim((string) $city);
        $address = trim((string) $address);

        if ($city !== '' && $address !== '') {
            return $city.', '.$address;
        }

        if ($city !== '') {
            return $city;
        }

        if ($address !== '') {
            return $address;
        }

        return trim((string) $fallback);
    }
}
