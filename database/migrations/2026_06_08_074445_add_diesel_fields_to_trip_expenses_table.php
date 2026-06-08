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
        Schema::table('trip_expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_expenses', 'fuel_liters')) {
                $table->decimal('fuel_liters', 10, 2)->nullable()->after('amount');
            }

            if (! Schema::hasColumn('trip_expenses', 'fuel_rate')) {
                $table->decimal('fuel_rate', 10, 2)->nullable()->after('fuel_liters');
            }

            if (! Schema::hasColumn('trip_expenses', 'odometer_start')) {
                $table->decimal('odometer_start', 12, 2)->nullable()->after('fuel_rate');
            }

            if (! Schema::hasColumn('trip_expenses', 'odometer_end')) {
                $table->decimal('odometer_end', 12, 2)->nullable()->after('odometer_start');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_expenses', function (Blueprint $table) {
            $columns = ['fuel_liters', 'fuel_rate', 'odometer_start', 'odometer_end'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('trip_expenses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
