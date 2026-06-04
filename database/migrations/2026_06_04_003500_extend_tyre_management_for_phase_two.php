<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tyres', function (Blueprint $table) {
            $table->dropUnique(['serial_number']);
            $table->string('size')->nullable()->after('brand');
            $table->date('purchase_date')->nullable()->after('purchase_cost');
            $table->text('warranty_details')->nullable()->after('purchase_date');
            $table->string('tyre_type')->nullable()->after('warranty_details');
            $table->date('scrapped_at')->nullable()->after('status');
            $table->text('scrap_notes')->nullable()->after('scrapped_at');
            $table->text('notes')->nullable()->after('scrap_notes');
            $table->unique(['tenant_id', 'serial_number']);
        });

        Schema::table('tyre_installations', function (Blueprint $table) {
            $table->decimal('running_km', 12, 2)->nullable()->after('km_at_removal');
        });

        Schema::create('tyre_rotations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('tyre_id')->constrained('tyres')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->date('rotation_date');
            $table->string('previous_position');
            $table->string('new_position');
            $table->text('reason')->nullable();
            $table->decimal('km_at_rotation', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('tyre_retreads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('tyre_id')->constrained('tyres')->cascadeOnDelete();
            $table->string('vendor');
            $table->decimal('cost', 12, 2)->default(0);
            $table->date('retread_date');
            $table->string('condition_after')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('tyre_damages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('tyre_id')->constrained('tyres')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('damage_type');
            $table->date('occurred_at');
            $table->text('description')->nullable();
            $table->decimal('repair_cost', 12, 2)->nullable();
            $table->boolean('is_repaired')->default(false);
            $table->timestamps();
        });

        Schema::create('tyre_replacements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('position_code');
            $table->foreignUuid('old_tyre_id')->constrained('tyres')->cascadeOnDelete();
            $table->foreignUuid('new_tyre_id')->nullable()->constrained('tyres')->nullOnDelete();
            $table->date('replaced_at');
            $table->text('reason')->nullable();
            $table->decimal('km_at_replacement', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('tyre_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('tyre_id')->constrained('tyres')->cascadeOnDelete();
            $table->string('expense_type');
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tyre_expenses');
        Schema::dropIfExists('tyre_replacements');
        Schema::dropIfExists('tyre_damages');
        Schema::dropIfExists('tyre_retreads');
        Schema::dropIfExists('tyre_rotations');

        Schema::table('tyre_installations', function (Blueprint $table) {
            $table->dropColumn('running_km');
        });

        Schema::table('tyres', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'serial_number']);
            $table->dropColumn([
                'size',
                'purchase_date',
                'warranty_details',
                'tyre_type',
                'scrapped_at',
                'scrap_notes',
                'notes',
            ]);
            $table->unique('serial_number');
        });
    }
};
