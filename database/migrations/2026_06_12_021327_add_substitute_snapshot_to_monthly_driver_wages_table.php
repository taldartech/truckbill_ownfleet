<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_driver_wages', function (Blueprint $table) {
            $table->unsignedSmallInteger('substitute_days')->default(0)->after('leave_days');
            $table->decimal('substitute_pay', 12, 2)->default(0)->after('substitute_days');
            $table->unsignedSmallInteger('substitute_liability_days')->default(0)->after('substitute_pay');
            $table->decimal('substitute_liability_pay', 12, 2)->default(0)->after('substitute_liability_days');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_driver_wages', function (Blueprint $table) {
            $table->dropColumn([
                'substitute_days',
                'substitute_pay',
                'substitute_liability_days',
                'substitute_liability_pay',
            ]);
        });
    }
};
