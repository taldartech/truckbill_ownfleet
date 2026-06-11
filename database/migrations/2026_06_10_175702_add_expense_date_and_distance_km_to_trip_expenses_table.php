<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_expenses', function (Blueprint $table) {
            $table->date('expense_date')->nullable()->after('expense_type');
            $table->decimal('distance_km', 10, 2)->nullable()->after('odometer_end');
        });
    }

    public function down(): void
    {
        Schema::table('trip_expenses', function (Blueprint $table) {
            $table->dropColumn(['expense_date', 'distance_km']);
        });
    }
};
