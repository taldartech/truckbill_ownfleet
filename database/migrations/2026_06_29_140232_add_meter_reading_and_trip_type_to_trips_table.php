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
        Schema::table('trips', function (Blueprint $table) {
            $table->string('trip_type', 20)->default('ROUND_TRIP')->after('start_station_id');
            $table->decimal('start_meter_reading_km', 12, 2)->nullable()->after('actual_arrival_at');
            $table->decimal('end_meter_reading_km', 12, 2)->nullable()->after('start_meter_reading_km');
            $table->decimal('load_weight_ton', 10, 3)->nullable()->after('end_meter_reading_km');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'trip_type',
                'start_meter_reading_km',
                'end_meter_reading_km',
                'load_weight_ton',
            ]);
        });
    }
};
