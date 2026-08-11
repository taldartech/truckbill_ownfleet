<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'customer_snapshot')) {
                $table->json('customer_snapshot')->nullable()->after('customer_id');
            }

            if (! Schema::hasColumn('invoices', 'trip_snapshot')) {
                $table->json('trip_snapshot')->nullable()->after('trip_id');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoice_items', 'consignor')) {
                $table->string('consignor')->nullable()->after('sequence_no');
            }

            if (! Schema::hasColumn('invoice_items', 'consignee')) {
                $table->string('consignee')->nullable()->after('consignor');
            }

            if (! Schema::hasColumn('invoice_items', 'from_address')) {
                $table->string('from_address')->nullable()->after('consignee');
            }

            if (! Schema::hasColumn('invoice_items', 'to_address')) {
                $table->string('to_address')->nullable()->after('from_address');
            }

            if (! Schema::hasColumn('invoice_items', 'route_legs_snapshot')) {
                $table->json('route_legs_snapshot')->nullable()->after('freight_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('invoices', 'customer_snapshot')) {
                $table->dropColumn('customer_snapshot');
            }

            if (Schema::hasColumn('invoices', 'trip_snapshot')) {
                $table->dropColumn('trip_snapshot');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            if (Schema::hasColumn('invoice_items', 'consignor')) {
                $table->dropColumn('consignor');
            }

            if (Schema::hasColumn('invoice_items', 'consignee')) {
                $table->dropColumn('consignee');
            }

            if (Schema::hasColumn('invoice_items', 'from_address')) {
                $table->dropColumn('from_address');
            }

            if (Schema::hasColumn('invoice_items', 'to_address')) {
                $table->dropColumn('to_address');
            }

            if (Schema::hasColumn('invoice_items', 'route_legs_snapshot')) {
                $table->dropColumn('route_legs_snapshot');
            }
        });
    }
};

