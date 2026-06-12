<?php

namespace App\Http\Controllers\Api;

use App\Enums\PayrollPayDaysMode;
use App\Http\Controllers\Controller;
use App\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollSettingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user !== null
            && $user->hasPermission('wages.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null,
            403,
        );

        $tenant = Tenant::query()->findOrFail($user->effectiveTenantId());

        return response()->json([
            'settings' => $this->formatSettings($tenant),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user !== null
            && $user->hasPermission('wages.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null,
            403,
        );

        $validated = $request->validate([
            'payroll_pay_days_mode' => ['required', Rule::enum(PayrollPayDaysMode::class)],
        ]);

        $tenant = Tenant::query()->findOrFail($user->effectiveTenantId());
        $tenant->payroll_pay_days_mode = $validated['payroll_pay_days_mode'];
        $tenant->save();

        return response()->json([
            'settings' => $this->formatSettings($tenant),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function formatSettings(Tenant $tenant): array
    {
        $mode = $tenant->payroll_pay_days_mode ?? PayrollPayDaysMode::Calendar;

        return [
            'payroll_pay_days_mode' => $mode instanceof PayrollPayDaysMode
                ? $mode->value
                : (string) $mode,
        ];
    }
}
