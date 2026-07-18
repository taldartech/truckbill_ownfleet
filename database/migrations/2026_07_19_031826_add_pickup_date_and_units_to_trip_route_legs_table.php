<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_route_legs', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_route_legs', 'pickup_date')) {
                $table->date('pickup_date')->nullable()->after('goods_name');
            }

            if (! Schema::hasColumn('trip_route_legs', 'no_of_units')) {
                $table->unsignedInteger('no_of_units')->nullable()->after('pickup_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trip_route_legs', function (Blueprint $table) {
            if (Schema::hasColumn('trip_route_legs', 'no_of_units')) {
                $table->dropColumn('no_of_units');
            }

            if (Schema::hasColumn('trip_route_legs', 'pickup_date')) {
                $table->dropColumn('pickup_date');
            }
        });
    }
};
