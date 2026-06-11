<?php

namespace App\Support;

use App\TruckType;
use Illuminate\Support\Str;

class TruckTypeDefaults
{
    /**
     * @return list<array{name: string, code: string}>
     */
    public static function presets(): array
    {
        return [
            ['name' => 'Truck', 'code' => 'TRUCK'],
            ['name' => 'Trailer', 'code' => 'TRAILER'],
            ['name' => 'Tanker', 'code' => 'TANKER'],
            ['name' => 'Container', 'code' => 'CONTAINER'],
        ];
    }

    public static function seedForTenant(string $tenantId): void
    {
        if (TruckType::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        foreach (self::presets() as $preset) {
            TruckType::query()->create([
                'tenant_id' => $tenantId,
                'name' => $preset['name'],
                'code' => $preset['code'],
                'is_active' => true,
            ]);
        }
    }

    public static function defaultCode(): string
    {
        return 'TRUCK';
    }

    public static function generateCode(string $name, string $tenantId, ?int $ignoreId = null): string
    {
        $base = Str::upper(Str::slug($name, '_'));
        if ($base === '') {
            $base = 'TYPE';
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
        $query = TruckType::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', $code);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
