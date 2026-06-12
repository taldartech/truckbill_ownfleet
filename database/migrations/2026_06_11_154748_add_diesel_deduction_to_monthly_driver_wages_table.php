<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_driver_wages', function (Blueprint $table) {
            $table->decimal('diesel_deduction', 12, 2)->default(0)->after('advance_deducted');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_driver_wages', function (Blueprint $table) {
            $table->dropColumn('diesel_deduction');
        });
    }
};
