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
        Schema::create('tyre_installations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('tyre_id')->constrained('tyres')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('position_code');
            $table->date('installed_at');
            $table->date('removed_at')->nullable();
            $table->decimal('km_at_installation', 12, 2)->default(0);
            $table->decimal('km_at_removal', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tyre_installations');
    }
};
