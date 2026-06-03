<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_driver_wages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->date('month');
            $table->unsignedInteger('total_days')->default(0);
            $table->unsignedInteger('payable_days')->default(0);
            $table->decimal('total_wage', 12, 2)->default(0);
            $table->string('payment_status', 20)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->text('payment_notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignUuid('created_by')->nullable();
            $table->foreignUuid('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'driver_id', 'month']);
            $table->index(['tenant_id', 'month', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_driver_wages');
    }
};
