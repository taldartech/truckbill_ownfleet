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

    public static function generateCode(string $name, string $tenantId, ?int $ignoreId = null): string
    {
        $base = Str::snake(Str::lower(trim($name)));
        $base = preg_replace('/[^a-z0-9_]+/', '_', $base) ?? 'goods';
        $base = trim($base, '_');

        if ($base === '') {
            $base = 'goods';
        }

        $code = $base;
        $suffix = 1;

        while (self::codeExists($tenantId, $code, $ignoreId)) {
            $code = $base.'_'.$suffix;
            $suffix++;
        }

        return $code;
    }

    private static function codeExists(string $tenantId, string $code, ?int $ignoreId): bool
    {
        $query = GoodsType::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', $code);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
