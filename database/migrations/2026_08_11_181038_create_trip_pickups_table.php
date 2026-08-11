<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_pickups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->string('pickup_code', 32);
            $table->unsignedInteger('sequence_no')->default(1);
            $table->foreignId('station_id')->constrained('stations')->restrictOnDelete();
            $table->string('consignor')->nullable();
            $table->text('from_address')->nullable();
            $table->string('goods_name')->nullable();
            $table->unsignedInteger('no_of_units')->nullable();
            $table->decimal('goods_value', 12, 2)->nullable();
            $table->string('ewaybill_no', 64)->nullable();
            $table->date('ewaybill_expiry')->nullable();
            $table->date('pickup_date')->nullable();
            $table->decimal('weight_ton', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['trip_id', 'pickup_code']);
            $table->index(['trip_id', 'sequence_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_pickups');
    }
};
