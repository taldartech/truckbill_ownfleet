<?php

namespace App\Support;

use App\GoodsType;
use Illuminate\Support\Str;

class GoodsTypeDefaults
{
    /**
     * No preset seed — company starts empty; users add goods types via Master / quick-add.
     */
    public static function seedForTenant(string $tenantId): void
    {
        // intentionally empty
    }
}
