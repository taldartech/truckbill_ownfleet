<?php

namespace App\Support;

use App\Tenant;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubscriptionExpiredResponse
{
    public static function forGraceRestrictedAction(Request $request, ?Tenant $tenant = null): RedirectResponse
    {
        return redirect()->route('app.dashboard');
    }

    public static function forLocked(Request $request): RedirectResponse
    {
        return redirect()->route('app.dashboard');
    }

    public static function abortUnlessCanMutate(Request $request, ?Tenant $tenant = null): void
    {
        $payload = $tenant?->subscriptionAccessPayload() ?? [];
        $mode = $payload['access_mode'] ?? 'locked';

        if ($mode === 'grace') {
            throw new HttpResponseException(self::forGraceRestrictedAction($request, $tenant));
        }

        throw new HttpResponseException(self::forLocked($request));
    }
}
