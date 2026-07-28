<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionAccessMode;
use App\Services\SubscriptionAccessService;
use App\Support\SubscriptionExpiredResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    public function __construct(
        private readonly SubscriptionAccessService $accessService,
    ) {}

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

        if ($tenant === null) {
            return $next($request);
        }

        $mode = $this->accessService->resolveForTenant($tenant);

        if ($mode === SubscriptionAccessMode::Full) {
            return $next($request);
        }

        if ($mode === SubscriptionAccessMode::GraceReadOnly) {
            if ($this->allowsGraceRead($request)) {
                return $next($request);
            }

            return SubscriptionExpiredResponse::forGraceRestrictedAction($request, $tenant);
        }

        return SubscriptionExpiredResponse::forLocked($request);
    }

    private function allowsGraceRead(Request $request): bool
    {
        return $request->isMethodSafe() && ! $this->isExportOrPrintRoute($request);
    }

    private function isExportOrPrintRoute(Request $request): bool
    {
        $name = (string) $request->route()?->getName();

        return str_contains($name, 'export')
            || str_contains($name, 'print')
            || str_contains($name, '-export');
    }
}
