<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['trip_route_leg_id']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignUuid('trip_route_leg_id')
                ->nullable()
                ->change();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreign('trip_route_leg_id')
                ->references('id')
                ->on('trip_route_legs')
                ->restrictOnDelete();
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('total_custom_amount', 12, 2)
                ->default(0)
                ->after('total_freight_amount');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('total_custom_amount');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['trip_route_leg_id']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignUuid('trip_route_leg_id')
                ->nullable(false)
                ->change();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreign('trip_route_leg_id')
                ->references('id')
                ->on('trip_route_legs')
                ->restrictOnDelete();
        });
    }
};
