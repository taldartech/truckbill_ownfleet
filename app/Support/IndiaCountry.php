<?php

namespace App\Support;

use App\Country;
use RuntimeException;

class IndiaCountry
{
    public static function id(): int
    {
        $id = Country::query()->where('code', 'IN')->value('id');

        if ($id === null) {
            throw new RuntimeException('India country record is missing. Run IndiaLocationSeeder.');
        }

        return (int) $id;
    }
}
