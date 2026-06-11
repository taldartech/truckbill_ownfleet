<?php

namespace App\Support;

use App\TyreLayout;
use Illuminate\Support\Str;

class TyreLayoutDefaults
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function presets(): array
    {
        return [
            [
                'code' => 'TYRES_6',
                'name' => '6 tyres — small / medium truck',
                'layout_type' => 'standard',
                'steer_axles' => 1,
                'dual_axles' => [],
                'single_axles' => [1],
                'spare_count' => 2,
                'sort_order' => 10,
            ],
            [
                'code' => 'TYRES_10',
                'name' => '10 tyres — heavy rigid (2 steer + 2 dual axles)',
                'layout_type' => 'standard',
                'steer_axles' => 1,
                'dual_axles' => [1, 2],
                'single_axles' => [],
                'spare_count' => 0,
                'sort_order' => 20,
            ],
            [
                'code' => 'TYRES_12',
                'name' => '12 tyres — multi-axle + 2 spare',
                'layout_type' => 'standard',
                'steer_axles' => 1,
                'dual_axles' => [1, 2],
                'single_axles' => [],
                'spare_count' => 2,
                'sort_order' => 30,
            ],
            [
                'code' => 'TYRES_14',
                'name' => '14 tyres — 2 steer + 3 dual axles',
                'layout_type' => 'standard',
                'steer_axles' => 1,
                'dual_axles' => [1, 2, 3],
                'single_axles' => [],
                'spare_count' => 0,
                'sort_order' => 40,
            ],
            [
                'code' => 'TYRES_16',
                'name' => '16 tyres — 3 dual axles + 2 spare',
                'layout_type' => 'standard',
                'steer_axles' => 1,
                'dual_axles' => [1, 2, 3],
                'single_axles' => [],
                'spare_count' => 2,
                'sort_order' => 50,
            ],
            [
                'code' => 'TYRES_18',
                'name' => '18 tyres — 2 steer + 4 dual axles',
                'layout_type' => 'standard',
                'steer_axles' => 1,
                'dual_axles' => [1, 2, 3, 4],
                'single_axles' => [],
                'spare_count' => 0,
                'sort_order' => 60,
            ],
            [
                'code' => 'TYRES_22',
                'name' => '22 tyres — long trailer / articulated',
                'layout_type' => 'standard',
                'steer_axles' => 1,
                'dual_axles' => [1, 2, 3, 4, 5],
                'single_axles' => [],
                'spare_count' => 0,
                'sort_order' => 70,
            ],
            [
                'code' => 'LEGACY_DUAL_REAR',
                'name' => 'Legacy (front + dual rear + 1 spare)',
                'layout_type' => 'legacy_dual_rear',
                'steer_axles' => 0,
                'dual_axles' => [],
                'single_axles' => [],
                'spare_count' => 0,
                'sort_order' => 100,
            ],
        ];
    }

    public static function seedForTenant(string $tenantId): void
    {
        if (TyreLayout::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        foreach (self::presets() as $preset) {
            TyreLayout::query()->create([
                'tenant_id' => $tenantId,
                ...$preset,
            ]);
        }
    }

    public static function defaultCode(): string
    {
        return 'TYRES_10';
    }

    public static function generateCode(string $name, string $tenantId, ?string $ignoreId = null): string
    {
        $base = Str::upper(Str::slug($name, '_'));
        if ($base === '') {
            $base = 'LAYOUT';
        }

        $code = $base;
        $suffix = 1;

        while (self::codeExists($tenantId, $code, $ignoreId)) {
            $code = $base.'_'.$suffix;
            $suffix++;
        }

        return $code;
    }

    private static function codeExists(string $tenantId, string $code, ?string $ignoreId): bool
    {
        $query = TyreLayout::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', $code);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
