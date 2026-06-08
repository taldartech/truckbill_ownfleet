<?php

use App\DocumentAlertSetting;
use App\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('document_alerts_live')->default(false)->after('user_role_id');
            $table->boolean('document_alerts_email')->default(false)->after('document_alerts_live');
        });

        DocumentAlertSetting::query()
            ->withoutGlobalScope('tenant')
            ->get()
            ->each(function (DocumentAlertSetting $settings): void {
                $recipientIds = collect($settings->recipient_user_ids ?? [])
                    ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
                    ->values()
                    ->all();

                if ($recipientIds === []) {
                    return;
                }

                User::query()
                    ->withoutGlobalScope('tenant')
                    ->where('tenant_id', $settings->tenant_id)
                    ->whereIn('id', $recipientIds)
                    ->update([
                        'document_alerts_live' => $settings->notify_live,
                        'document_alerts_email' => $settings->notify_email,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['document_alerts_live', 'document_alerts_email']);
        });
    }
};
