<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'plan_end_date')) {
                $table->dropColumn('plan_end_date');
            }
            if (Schema::hasColumn('tenants', 'lr_credits')) {
                $table->dropColumn('lr_credits');
            }
            if (Schema::hasColumn('tenants', 'truck_credits')) {
                $table->dropColumn('truck_credits');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'plan_end_date')) {
                $table->date('plan_end_date')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'lr_credits')) {
                $table->integer('lr_credits')->default(0);
            }
            if (! Schema::hasColumn('tenants', 'truck_credits')) {
                $table->integer('truck_credits')->default(0);
            }
        });
    }
};
