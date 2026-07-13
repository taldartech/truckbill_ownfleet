<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignUuid('trip_route_leg_id')->constrained('trip_route_legs')->restrictOnDelete();
            $table->unsignedInteger('sequence_no')->default(1);
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
            $table->string('goods_name')->nullable();
            $table->decimal('weight_ton', 12, 3)->default(0);
            $table->decimal('freight_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index('trip_route_leg_id');
            $table->index(['invoice_id', 'sequence_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
