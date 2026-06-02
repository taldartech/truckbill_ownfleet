<?php

namespace App\Support;

use App\User;

/**
 * Request-scoped tenant id: set once by ResolveTenant middleware, read everywhere else.
 */
class CurrentTenant
{
    public static function id(): ?string
    {
        $fromContext = TenantContext::tenantId();

        if ($fromContext !== null) {
            return $fromContext;
        }

        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        return $user->effectiveTenantId();
    }

    public static function requireId(): string
    {
        $id = self::id();

        if ($id === null) {
            abort(403, 'Company context is required.');
        }

        return $id;
    }
}
