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
        Schema::create('driver_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->date('assigned_from');
            $table->date('assigned_to')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->foreignUuid('created_by')->nullable();
            $table->foreignUuid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'vehicle_id', 'assigned_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_assignments');
    }
};
