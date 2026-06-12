<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_driver_wages', function (Blueprint $table) {
            $table->decimal('bonus', 12, 2)->default(0)->after('gross_earning');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_driver_wages', function (Blueprint $table) {
            $table->dropColumn('bonus');
        });
    }
};
