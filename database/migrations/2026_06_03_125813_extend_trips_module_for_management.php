<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->string('customer_name')->nullable()->after('trip_number');
            $table->string('broker_name')->nullable()->after('customer_name');
            $table->decimal('loaded_distance_km', 12, 2)->default(0)->after('total_distance_km');
            $table->decimal('empty_distance_km', 12, 2)->default(0)->after('loaded_distance_km');
        });

        Schema::table('trip_route_legs', function (Blueprint $table) {
            $table->string('goods_name')->nullable()->after('distance_km');
            $table->string('consignor')->nullable()->after('goods_name');
            $table->string('consignee')->nullable()->after('consignor');
            $table->decimal('freight_rate', 12, 2)->nullable()->after('consignee');
            $table->decimal('weight_ton', 12, 3)->nullable()->after('freight_rate');
            $table->string('invoice_number')->nullable()->after('weight_ton');
            $table->string('lr_number')->nullable()->after('invoice_number');
            $table->text('remarks')->nullable()->after('lr_number');
            $table->decimal('freight_amount', 12, 2)->default(0)->after('remarks');
            $table->decimal('advance_received', 12, 2)->default(0)->after('freight_amount');
            $table->decimal('balance_amount', 12, 2)->default(0)->after('advance_received');
            $table->string('payment_status', 20)->default('pending')->after('balance_amount');
            $table->string('billing_status', 20)->default('unbilled')->after('payment_status');
        });

        Schema::table('trip_expenses', function (Blueprint $table) {
            $table->foreignUuid('trip_route_leg_id')
                ->nullable()
                ->after('trip_id')
                ->constrained('trip_route_legs')
                ->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->after('notes');
            $table->foreignUuid('updated_by')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('trip_expenses', function (Blueprint $table) {
            $table->dropForeign(['trip_route_leg_id']);
            $table->dropColumn(['trip_route_leg_id', 'created_by', 'updated_by']);
        });

        Schema::table('trip_route_legs', function (Blueprint $table) {
            $table->dropColumn([
                'goods_name',
                'consignor',
                'consignee',
                'freight_rate',
                'weight_ton',
                'invoice_number',
                'lr_number',
                'remarks',
                'freight_amount',
                'advance_received',
                'balance_amount',
                'payment_status',
                'billing_status',
            ]);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'customer_name',
                'broker_name',
                'loaded_distance_km',
                'empty_distance_km',
            ]);
        });
    }
};
