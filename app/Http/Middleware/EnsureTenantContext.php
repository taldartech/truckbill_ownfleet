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

        if ($user !== null && $user->requiresBranchSelection()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Select a branch before continuing.',
                    'redirect' => '/app/branches',
                ], 403);
            }

            return redirect('/app/branches');
        }

        return $next($request);
    }
}
