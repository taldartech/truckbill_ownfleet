<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trips') && ! Schema::hasColumn('trips', 'deletion_reason')) {
            Schema::table('trips', function (Blueprint $table): void {
                $table->text('deletion_reason')->nullable()->after('deleted_by');
            });
        }

        if (Schema::hasTable('trip_audit_logs')) {
            return;
        }

        Schema::create('trip_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->string('event_type', 64);
            $table->string('subject_type', 128)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->json('payload')->nullable();
            $table->foreignUuid('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->timestamps();

            $table->index(['trip_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_audit_logs');

        if (Schema::hasTable('trips') && Schema::hasColumn('trips', 'deletion_reason')) {
            Schema::table('trips', function (Blueprint $table): void {
                $table->dropColumn('deletion_reason');
            });
        }
    }
};
