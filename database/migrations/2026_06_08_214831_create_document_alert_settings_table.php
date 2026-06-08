<?php

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
        Schema::create('document_alert_settings', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')->primary()->constrained('tenants')->cascadeOnDelete();
            $table->boolean('notify_live')->default(true);
            $table->boolean('notify_email')->default(true);
            $table->json('recipient_user_ids')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_alert_settings');
    }
};
