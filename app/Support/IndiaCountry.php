<?php

namespace App\Support;

use App\Country;

class IndiaCountry
{
    public static function id(): int
    {
        return self::record()->id;
    }

    public static function record(): Country
    {
        return Country::query()->firstOrCreate(
            ['code' => 'IN'],
            ['name' => 'India'],
        );
    }
}
