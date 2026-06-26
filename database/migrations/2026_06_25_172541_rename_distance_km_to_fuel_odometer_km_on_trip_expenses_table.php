<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_expenses', 'fuel_odometer_km')) {
                $table->decimal('fuel_odometer_km', 12, 2)->nullable()->after('fuel_rate');
            }
        });

        if (Schema::hasColumn('trip_expenses', 'distance_km')) {
            DB::table('trip_expenses')
                ->whereNotNull('distance_km')
                ->update(['fuel_odometer_km' => DB::raw('distance_km')]);
        }

        Schema::table('trip_expenses', function (Blueprint $table) {
            $columnsToDrop = array_filter(
                ['distance_km', 'odometer_start', 'odometer_end', 'fuel_km'],
                fn (string $column): bool => Schema::hasColumn('trip_expenses', $column),
            );

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('trip_expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_expenses', 'distance_km')) {
                $table->decimal('distance_km', 10, 2)->nullable()->after('fuel_rate');
            }

            if (! Schema::hasColumn('trip_expenses', 'odometer_start')) {
                $table->decimal('odometer_start', 12, 2)->nullable()->after('distance_km');
            }

            if (! Schema::hasColumn('trip_expenses', 'odometer_end')) {
                $table->decimal('odometer_end', 12, 2)->nullable()->after('odometer_start');
            }
        });

        if (Schema::hasColumn('trip_expenses', 'fuel_odometer_km')) {
            DB::table('trip_expenses')
                ->whereNotNull('fuel_odometer_km')
                ->update(['distance_km' => DB::raw('fuel_odometer_km')]);
        }

        Schema::table('trip_expenses', function (Blueprint $table) {
            if (Schema::hasColumn('trip_expenses', 'fuel_odometer_km')) {
                $table->dropColumn('fuel_odometer_km');
            }
        });
    }
};
