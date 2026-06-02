<?php

namespace App\Support;

class SelectedCompanySession
{
    public const TENANT_KEY = 'super_admin_tenant_id';

    public const LEGACY_COMPANY_KEY = 'super_admin_company_id';

    public static function id(): ?string
    {
        $tenantId = session(self::TENANT_KEY);
        $companyId = session(self::LEGACY_COMPANY_KEY);

        if ($tenantId !== null) {
            return (string) $tenantId;
        }

        if ($companyId !== null) {
            return (string) $companyId;
        }

        return null;
    }

    public static function set(?string $tenantId): void
    {
        if ($tenantId === null) {
            self::forget();

            return;
        }

        session([
            self::TENANT_KEY => $tenantId,
            self::LEGACY_COMPANY_KEY => $tenantId,
        ]);
    }

    public static function forget(): void
    {
        session()->forget([
            self::TENANT_KEY,
            self::LEGACY_COMPANY_KEY,
        ]);
    }
}
