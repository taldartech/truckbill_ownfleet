<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_route_legs', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_route_legs', 'expected_mileage_km_per_liter')) {
                $table->decimal('expected_mileage_km_per_liter', 8, 2)->nullable()->after('freight_rate');
            }
        });

        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'expected_mileage_km_per_liter')) {
                $table->decimal('expected_mileage_km_per_liter', 8, 2)->nullable()->after('odometer_km');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trip_route_legs', function (Blueprint $table) {
            if (Schema::hasColumn('trip_route_legs', 'expected_mileage_km_per_liter')) {
                $table->dropColumn('expected_mileage_km_per_liter');
            }
        });

        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'expected_mileage_km_per_liter')) {
                $table->dropColumn('expected_mileage_km_per_liter');
            }
        });
    }
};
