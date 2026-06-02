<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\FleetDashboardStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetDashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user !== null && $user->hasPermission('dashboard.view'), 403);

        if (! $user->canAccessCompanyPanel() || $user->effectiveTenantId() === null) {
            return response()->json([
                'fleet' => null,
                'requires_company' => true,
                'generated_at' => now()->toISOString(),
            ]);
        }

        return response()->json(FleetDashboardStats::build($user));
    }
}
