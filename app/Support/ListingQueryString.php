<?php

namespace App\Support;

use Illuminate\Http\Request;

class ListingQueryString
{
    /**
     * @var array<int, string>
     */
    public const FREIGHT_BOOKING_KEYS = [
        'search',
        'start_date',
        'end_date',
        'pending_memo',
        'status',
        'sort_by',
        'sort_direction',
        'page',
        'per_page',
    ];

    /**
     * @var array<int, string>
     */
    public const LORRY_RECEIPT_KEYS = [
        'search',
        'start_date',
        'end_date',
        'status',
        'sort_by',
        'sort_direction',
        'page',
        'per_page',
    ];

    /**
     * @param  array<int, string>  $allowedKeys
     * @return array<string, scalar|null>
     */
    public static function params(Request $request, array $allowedKeys): array
    {
        /** @var array<string, mixed> $queryParams */
        $queryParams = $request->query();
        $whitelisted = [];

        foreach ($allowedKeys as $key) {
            if (! array_key_exists($key, $queryParams)) {
                continue;
            }

            $value = $queryParams[$key];

            if (is_array($value) || is_object($value)) {
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            $whitelisted[$key] = $value;
        }

        return $whitelisted;
    }

    /**
     * @param  array<int, string>  $allowedKeys
     */
    public static function currentSuffix(array $allowedKeys): string
    {
        $params = self::params(request(), $allowedKeys);

        if ($params === []) {
            return '';
        }

        return '?'.http_build_query($params);
    }

    /**
     * @param  array<int, string>  $allowedKeys
     */
    public static function scalarValue(Request $request, string $key, array $allowedKeys): string
    {
        if (! in_array($key, $allowedKeys, true)) {
            return '';
        }

        /** @var mixed $value */
        $value = $request->query($key);

        if (is_array($value) || is_object($value) || $value === null) {
            return '';
        }

        return (string) $value;
    }
}
