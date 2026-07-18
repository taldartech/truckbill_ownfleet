<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_route_legs', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_route_legs', 'ewaybill_no')) {
                $table->string('ewaybill_no', 64)->nullable()->after('distance_km');
            }

            if (! Schema::hasColumn('trip_route_legs', 'goods_value')) {
                $table->decimal('goods_value', 12, 2)->nullable()->after('ewaybill_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trip_route_legs', function (Blueprint $table) {
            if (Schema::hasColumn('trip_route_legs', 'goods_value')) {
                $table->dropColumn('goods_value');
            }

            if (Schema::hasColumn('trip_route_legs', 'ewaybill_no')) {
                $table->dropColumn('ewaybill_no');
            }
        });
    }
};
