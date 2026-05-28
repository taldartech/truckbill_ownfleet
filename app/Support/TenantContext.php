<?php

namespace App\Support;

class TenantContext
{
    private static ?string $tenantId = null;

    public static function setTenantId(?string $tenantId): void
    {
        self::$tenantId = $tenantId;
    }

    public static function tenantId(): ?string
    {
        return self::$tenantId;
    }
}
