<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tyres', function (Blueprint $table) {
            $table->decimal('expected_life_km', 12, 2)->default(40000)->after('current_km');
        });

        Schema::table('tyre_installations', function (Blueprint $table) {
            $table->text('removal_recommendation')->nullable()->after('running_km');
        });

        Schema::table('tyre_damages', function (Blueprint $table) {
            $table->string('position_code')->nullable()->after('vehicle_id');
            $table->decimal('km_at_occurrence', 12, 2)->nullable()->after('occurred_at');
        });

        Schema::table('tyre_replacements', function (Blueprint $table) {
            $table->decimal('old_tyre_stint_km', 12, 2)->nullable()->after('km_at_replacement');
            $table->decimal('old_tyre_remaining_life_km', 12, 2)->nullable()->after('old_tyre_stint_km');
            $table->text('recommendation')->nullable()->after('old_tyre_remaining_life_km');
        });
    }

    public function down(): void
    {
        Schema::table('tyre_replacements', function (Blueprint $table) {
            $table->dropColumn(['old_tyre_stint_km', 'old_tyre_remaining_life_km', 'recommendation']);
        });

        Schema::table('tyre_damages', function (Blueprint $table) {
            $table->dropColumn(['position_code', 'km_at_occurrence']);
        });

        Schema::table('tyre_installations', function (Blueprint $table) {
            $table->dropColumn('removal_recommendation');
        });

        Schema::table('tyres', function (Blueprint $table) {
            $table->dropColumn('expected_life_km');
        });
    }
};
