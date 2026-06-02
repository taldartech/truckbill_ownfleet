<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $request->header('X-Tenant-Id');

        if ($request->user() !== null) {
            $request->user()->loadMissing('userRole.permissions');

            if ($tenantId === null) {
                $tenantId = $request->user()->effectiveTenantId();
            }
        }

        TenantContext::setTenantId($tenantId);

        return $next($request);
    }
}
