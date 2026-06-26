<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_maintenances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->date('service_date');
            $table->decimal('odometer_km', 12, 2);
            $table->string('service_type');
            $table->string('service_provider')->nullable();
            $table->decimal('cost_amount', 12, 2)->default(0);
            $table->decimal('next_service_due_km', 12, 2)->nullable();
            $table->date('next_service_due_date')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'vehicle_id', 'service_date']);
            $table->index(['tenant_id', 'next_service_due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_maintenances');
    }
};
