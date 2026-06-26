<?php

namespace App\Http\Controllers\Api;

use App\Support\ApiDateTime;
use App\Http\Controllers\Controller;
use App\Support\FleetComplianceAlerts;
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
                'generated_at' => ApiDateTime::toIso(now()),
            ]);
        }

        $validated = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);

        $fromDate = $validated['from_date'] ?? now()->toDateString();
        $toDate = $validated['to_date'] ?? $fromDate;

        return response()->json(FleetDashboardStats::build($user, $fromDate, $toDate));
    }

    public function complianceAlerts(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user !== null && $user->hasPermission('dashboard.view'), 403);

        if (! $user->canAccessCompanyPanel() || $user->effectiveTenantId() === null) {
            return response()->json([
                'documents' => [],
                'licenses' => [],
                'requires_company' => true,
            ]);
        }

        return response()->json([
            ...FleetComplianceAlerts::build($user),
            'requires_company' => false,
        ]);
    }
}
