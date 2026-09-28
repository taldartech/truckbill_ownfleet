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
        // 1. Drop dead tables
        Schema::dropIfExists('trip_freights');
        Schema::dropIfExists('trip_master_data_tables');
        Schema::dropIfExists('tyre_retreads');

        // 2. Drop dead half_loaded_distance_km on trips
        if (Schema::hasColumn('trips', 'half_loaded_distance_km')) {
            Schema::table('trips', function (Blueprint $table) {
                $table->dropColumn('half_loaded_distance_km');
            });
        }

        // 3. Drop dead agency_id on stations, customers, brokers
        if (Schema::hasColumn('stations', 'agency_id')) {
            Schema::table('stations', function (Blueprint $table) {
                $table->dropColumn('agency_id');
            });
        }

        if (Schema::hasColumn('customers', 'agency_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('agency_id');
            });
        }

        if (Schema::hasColumn('brokers', 'agency_id')) {
            Schema::table('brokers', function (Blueprint $table) {
                $table->dropColumn('agency_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('brokers', 'agency_id')) {
            Schema::table('brokers', function (Blueprint $table) {
                $table->unsignedBigInteger('agency_id')->nullable()->after('city_id');
            });
        }

        if (! Schema::hasColumn('customers', 'agency_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unsignedBigInteger('agency_id')->nullable()->after('city_id');
            });
        }

        if (! Schema::hasColumn('stations', 'agency_id')) {
            Schema::table('stations', function (Blueprint $table) {
                $table->unsignedBigInteger('agency_id')->nullable()->after('city_id');
            });
        }

        if (! Schema::hasColumn('trips', 'half_loaded_distance_km')) {
            Schema::table('trips', function (Blueprint $table) {
                $table->decimal('half_loaded_distance_km', 12, 2)->default(0)->after('loaded_distance_km');
            });
        }

        if (! Schema::hasTable('trip_freights')) {
            Schema::create('trip_freights', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->foreignUuid('trip_id')->constrained('trips')->cascadeOnDelete();
                $table->string('freight_reference')->nullable();
                $table->decimal('weight_ton', 10, 2)->nullable();
                $table->decimal('amount', 12, 2);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('trip_master_data_tables')) {
            Schema::create('trip_master_data_tables', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tyre_retreads')) {
            Schema::create('tyre_retreads', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignUuid('tyre_id')->constrained('tyres')->cascadeOnDelete();
                $table->string('vendor');
                $table->decimal('cost', 12, 2)->default(0);
                $table->date('retread_date')->nullable();
                $table->string('condition_after')->nullable();
                $table->timestamps();
            });
        }
    }
};
