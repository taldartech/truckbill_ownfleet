<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_route_legs', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_route_legs', 'trip_pickup_id')) {
                $table->foreignUuid('trip_pickup_id')
                    ->nullable()
                    ->after('trip_id')
                    ->constrained('trip_pickups')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('trip_route_legs', 'drop_code')) {
                $table->string('drop_code', 32)->nullable()->after('trip_pickup_id');
            }

            if (! Schema::hasColumn('trip_route_legs', 'delivery_status')) {
                $table->string('delivery_status', 32)->nullable()->after('billing_status');
            }
        });

        Schema::table('trip_route_legs', function (Blueprint $table) {
            $table->unique(['trip_id', 'drop_code']);
            $table->index('trip_pickup_id');
        });
    }

    public function down(): void
    {
        Schema::table('trip_route_legs', function (Blueprint $table) {
            if (Schema::hasColumn('trip_route_legs', 'trip_pickup_id')) {
                $table->dropForeign(['trip_pickup_id']);
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('trip_route_legs', 'trip_pickup_id') ? 'trip_pickup_id' : null,
                Schema::hasColumn('trip_route_legs', 'drop_code') ? 'drop_code' : null,
                Schema::hasColumn('trip_route_legs', 'delivery_status') ? 'delivery_status' : null,
            ]));

            if ($columns !== []) {
                $table->dropUnique(['trip_id', 'drop_code']);
                $table->dropColumn($columns);
            }
        });
    }
};
