<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Batteries Master Table
        Schema::create('batteries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->string('serial_number');
            $table->string('brand');
            $table->string('model')->nullable();
            $table->string('battery_type')->default('LEAD_ACID');
            $table->string('voltage')->default('12V');
            $table->decimal('capacity_ah', 8, 2)->default(150.00);
            $table->date('purchase_date');
            $table->decimal('purchase_cost', 12, 2)->default(0.00);
            $table->unsignedInteger('warranty_months')->default(24);
            $table->date('warranty_valid_until')->nullable();
            $table->unsignedInteger('expected_life_months')->default(36);
            $table->string('supplier_name')->nullable();
            $table->string('status')->default('IN_STOCK');
            $table->date('scrapped_at')->nullable();
            $table->decimal('scrap_sale_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable();
            $table->foreignUuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'serial_number']);
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'warranty_valid_until']);
            $table->index(['tenant_id', 'brand']);
        });

        // 2. Battery Installations (Stints on Vehicles)
        Schema::create('battery_installations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('battery_id')->constrained('batteries')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('slot_name')->default('Main #1');
            $table->date('installed_at');
            $table->date('removed_at')->nullable();
            $table->decimal('km_at_installation', 12, 2)->default(0.00);
            $table->decimal('km_at_removal', 12, 2)->nullable();
            $table->decimal('running_km', 12, 2)->nullable();
            $table->string('removal_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('installed_by')->nullable();
            $table->foreignUuid('removed_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'battery_id', 'removed_at']);
            $table->index(['tenant_id', 'vehicle_id', 'removed_at']);
        });

        // 3. Battery Replacements Log
        Schema::create('battery_replacements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('slot_name')->default('Main #1');
            $table->foreignUuid('old_battery_id')->constrained('batteries')->cascadeOnDelete();
            $table->foreignUuid('new_battery_id')->nullable()->constrained('batteries')->nullOnDelete();
            $table->date('replaced_at');
            $table->decimal('odometer_km', 12, 2)->default(0.00);
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'vehicle_id', 'replaced_at']);
            $table->index(['tenant_id', 'old_battery_id']);
            $table->index(['tenant_id', 'new_battery_id']);
        });

        // 4. Battery Inspections
        Schema::create('battery_inspections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('battery_id')->constrained('batteries')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->date('inspected_at');
            $table->decimal('voltage_reading', 5, 2)->nullable();
            $table->decimal('specific_gravity', 6, 3)->nullable();
            $table->string('condition')->default('GOOD');
            $table->string('action_taken')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'battery_id', 'inspected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battery_inspections');
        Schema::dropIfExists('battery_replacements');
        Schema::dropIfExists('battery_installations');
        Schema::dropIfExists('batteries');
    }
};
