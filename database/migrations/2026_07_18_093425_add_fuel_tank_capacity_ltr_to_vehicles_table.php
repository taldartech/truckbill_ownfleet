<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vehicles', 'fuel_tank_capacity_ltr')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $after = Schema::hasColumn('vehicles', 'max_load_ton')
                    ? 'max_load_ton'
                    : 'odometer_km';

                $table->decimal('fuel_tank_capacity_ltr', 10, 2)->default(0)->after($after);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vehicles', 'fuel_tank_capacity_ltr')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn('fuel_tank_capacity_ltr');
            });
        }
    }
};
