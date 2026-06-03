<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_month_locks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->date('month');
            $table->string('status', 20)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignUuid('closed_by')->nullable();
            $table->foreignUuid('locked_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'month']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_month_locks');
    }
};
