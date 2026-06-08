<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (! Schema::hasColumn('trips', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('trip_number');
            }

            if (! Schema::hasColumn('trips', 'broker_id')) {
                $table->unsignedBigInteger('broker_id')->nullable()->after('customer_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (Schema::hasColumn('trips', 'broker_id')) {
                $table->dropColumn('broker_id');
            }

            if (Schema::hasColumn('trips', 'customer_id')) {
                $table->dropColumn('customer_id');
            }
        });
    }
};
