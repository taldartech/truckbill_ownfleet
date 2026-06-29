<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->requiresCompanySelection()) {
            return $next($request);
        }

        $tenant = $user->resolvedCompany();
        if ($tenant !== null) {
            $tenant->loadMissing('subscription');
        }

        if ($tenant && ! $tenant->isPlanActive()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Subscription expired.'], 403);
            }

            if ($user->hasRole('super_admin')) {
                return redirect('/app/subscription')
                    ->with('error', 'Your subscription has expired. Please renew to continue.');
            }

            return redirect()->route('app.dashboard')
                ->with('error', 'Your subscription has expired. Please contact your super admin to renew.');
        }

        return $next($request);
    }
}
