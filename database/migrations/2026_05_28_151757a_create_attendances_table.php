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
        Schema::create('attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->foreignUuid('primary_driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignUuid('temporary_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignUuid('wage_owner_driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('status')->default('PRESENT');
            $table->decimal('wage_amount', 12, 2)->default(0);
            $table->foreignUuid('created_by')->nullable();
            $table->foreignUuid('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'vehicle_id', 'attendance_date']);
            $table->index(['tenant_id', 'attendance_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
