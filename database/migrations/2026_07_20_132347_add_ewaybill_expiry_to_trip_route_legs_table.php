<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_route_legs', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_route_legs', 'ewaybill_expiry')) {
                $table->date('ewaybill_expiry')->nullable()->after('ewaybill_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trip_route_legs', function (Blueprint $table) {
            if (Schema::hasColumn('trip_route_legs', 'ewaybill_expiry')) {
                $table->dropColumn('ewaybill_expiry');
            }
        });
    }
};
