<?php

namespace App\Http\Controllers\Api;

use App\DocumentAlertSetting;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentAlertSettingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user !== null
            && $user->hasPermission('users.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null,
            403,
        );

        $tenantId = (string) $user->effectiveTenantId();
        $settings = DocumentAlertSetting::query()->firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'notify_live' => true,
                'notify_email' => true,
                'recipient_user_ids' => [],
            ],
        );

        return response()->json([
            'settings' => $this->formatSettings($settings),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user !== null
            && $user->hasPermission('users.manage')
            && $user->canAccessCompanyPanel()
            && $user->effectiveTenantId() !== null,
            403,
        );

        $validated = $request->validate([
            'notify_live' => ['required', 'boolean'],
            'notify_email' => ['required', 'boolean'],
        ]);

        $tenantId = (string) $user->effectiveTenantId();

        $settings = DocumentAlertSetting::query()->updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'notify_live' => $validated['notify_live'],
                'notify_email' => $validated['notify_email'],
                'recipient_user_ids' => [],
            ],
        );

        return response()->json([
            'settings' => $this->formatSettings($settings),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSettings(DocumentAlertSetting $settings): array
    {
        return [
            'notify_live' => $settings->notify_live,
            'notify_email' => $settings->notify_email,
        ];
    }
}
