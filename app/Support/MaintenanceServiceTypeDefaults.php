<?php

namespace App\Support;

use App\MaintenanceServiceType;

class MaintenanceServiceTypeDefaults
{
    /**
     * @return list<array{name: string, code: string}>
     */
    public static function presets(): array
    {
        return [
            ['name' => 'General Service', 'code' => 'GENERAL_SERVICE'],
            ['name' => 'Oil Change', 'code' => 'OIL_CHANGE'],
            ['name' => 'Greasing & Lubrication', 'code' => 'GREASING_AND_LUBRICATION'],
            ['name' => 'Engine Repair', 'code' => 'ENGINE_REPAIR'],
            ['name' => 'Brake Repair', 'code' => 'BRAKE_REPAIR'],
            ['name' => 'Electrical Work', 'code' => 'ELECTRICAL_WORK'],
            ['name' => 'Body Work', 'code' => 'BODY_WORK'],
            ['name' => 'AC Repair', 'code' => 'AC_REPAIR'],
            ['name' => 'Other', 'code' => 'OTHER'],
        ];
    }

    public static function seedForTenant(string $tenantId): void
    {
        foreach (self::presets() as $preset) {
            MaintenanceServiceType::query()
                ->withoutGlobalScopes()
                ->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'code' => $preset['code'],
                    ],
                    [
                        'name' => $preset['name'],
                        'is_active' => true,
                    ],
                );
        }
    }
}
