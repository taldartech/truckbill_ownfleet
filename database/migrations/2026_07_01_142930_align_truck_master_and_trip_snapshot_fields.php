<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vehicles', 'expected_mileage_km_per_liter')
            && ! Schema::hasColumn('vehicles', 'default_mileage_km_per_liter')) {
            Schema::table('vehicles', function (Blueprint $table): void {
                $table->renameColumn('expected_mileage_km_per_liter', 'default_mileage_km_per_liter');
            });
        }

        Schema::table('vehicles', function (Blueprint $table): void {
            if (! Schema::hasColumn('vehicles', 'max_load_ton')) {
                $after = Schema::hasColumn('vehicles', 'default_mileage_km_per_liter')
                    ? 'default_mileage_km_per_liter'
                    : 'odometer_km';
                $table->decimal('max_load_ton', 10, 3)->default(0)->after($after);
            }
        });

        if (Schema::hasColumn('trips', 'expected_diesel_liters')
            && ! Schema::hasColumn('trips', 'planned_diesel_liters')) {
            Schema::table('trips', function (Blueprint $table): void {
                $table->renameColumn('expected_diesel_liters', 'planned_diesel_liters');
            });
        }

        Schema::table('trips', function (Blueprint $table): void {
            if (! Schema::hasColumn('trips', 'planned_mileage_km_per_liter')) {
                $table->decimal('planned_mileage_km_per_liter', 8, 2)->default(0)->after('load_weight_ton');
            }

            if (! Schema::hasColumn('trips', 'max_load_ton')) {
                $table->decimal('max_load_ton', 10, 3)->default(0)->after('planned_mileage_km_per_liter');
            }
        });

        if (! Schema::hasColumn('trip_route_legs', 'expected_mileage_km_per_liter')) {
            Schema::table('trip_route_legs', function (Blueprint $table): void {
                $table->decimal('expected_mileage_km_per_liter', 8, 2)->nullable()->after('freight_rate');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('trip_route_legs', 'expected_mileage_km_per_liter')) {
            Schema::table('trip_route_legs', function (Blueprint $table): void {
                $table->dropColumn('expected_mileage_km_per_liter');
            });
        }

        Schema::table('trips', function (Blueprint $table): void {
            if (Schema::hasColumn('trips', 'max_load_ton')) {
                $table->dropColumn('max_load_ton');
            }

            if (Schema::hasColumn('trips', 'planned_mileage_km_per_liter')) {
                $table->dropColumn('planned_mileage_km_per_liter');
            }
        });

        if (Schema::hasColumn('trips', 'planned_diesel_liters')
            && ! Schema::hasColumn('trips', 'expected_diesel_liters')) {
            Schema::table('trips', function (Blueprint $table): void {
                $table->renameColumn('planned_diesel_liters', 'expected_diesel_liters');
            });
        }

        Schema::table('vehicles', function (Blueprint $table): void {
            if (Schema::hasColumn('vehicles', 'max_load_ton')) {
                $table->dropColumn('max_load_ton');
            }
        });

        if (Schema::hasColumn('vehicles', 'default_mileage_km_per_liter')
            && ! Schema::hasColumn('vehicles', 'expected_mileage_km_per_liter')) {
            Schema::table('vehicles', function (Blueprint $table): void {
                $table->renameColumn('default_mileage_km_per_liter', 'expected_mileage_km_per_liter');
            });
        }
    }
};
