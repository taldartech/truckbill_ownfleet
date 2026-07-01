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
            $table->decimal('expected_diesel_liters', 10, 2)->nullable()->after('load_weight_ton');
            $table->decimal('diesel_rate_per_liter', 10, 2)->nullable()->after('expected_diesel_liters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'expected_diesel_liters',
                'diesel_rate_per_liter',
            ]);
        });
    }
};
