<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->requiresCompanySelection()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Select a company before continuing.',
                    'redirect' => '/app/companies',
                ], 403);
            }

            return redirect('/app/companies');
        }

        return $next($request);
    }
}
