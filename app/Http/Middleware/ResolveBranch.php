<?php

namespace App\Http\Middleware;

use App\Support\BranchContext;
use App\Support\RoleHierarchy;
use App\Support\SelectedBranchSession;
use App\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBranch
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $branchId = $request->header('X-Branch-Id');
        $user = $request->user();

        if ($user !== null) {
            if (RoleHierarchy::requiresBranchAssignment((int) $user->user_role_id)) {
                $branchId = $user->branch_id;
            } elseif ($user->user_role_id === UserRole::SUPER_ADMIN) {
                if ($branchId === null) {
                    $branchId = SelectedBranchSession::id();
                }
            }
        }

        BranchContext::setBranchId($branchId !== null && $branchId !== ''
            ? (int) $branchId
            : null);

        return $next($request);
    }
}
