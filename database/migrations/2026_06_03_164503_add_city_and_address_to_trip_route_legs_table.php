<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_route_legs', function (Blueprint $table) {
            $table->string('from_city')->nullable()->after('to_location');
            $table->text('from_address')->nullable()->after('from_city');
            $table->string('to_city')->nullable()->after('from_address');
            $table->text('to_address')->nullable()->after('to_city');
        });
    }

    public function down(): void
    {
        Schema::table('trip_route_legs', function (Blueprint $table) {
            $table->dropColumn([
                'from_city',
                'from_address',
                'to_city',
                'to_address',
            ]);
        });
    }
};
