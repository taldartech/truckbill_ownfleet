<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_payments', 'tds_amount')) {
                $table->decimal('tds_amount', 12, 2)->default(0)->after('amount');
            }
            if (! Schema::hasColumn('invoice_payments', 'deduction_amount')) {
                $table->decimal('deduction_amount', 12, 2)->default(0)->after('tds_amount');
            }
            if (! Schema::hasColumn('invoice_payments', 'deduction_reason')) {
                $table->string('deduction_reason', 255)->nullable()->after('deduction_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_payments', 'deduction_reason')) {
                $table->dropColumn('deduction_reason');
            }
            if (Schema::hasColumn('invoice_payments', 'deduction_amount')) {
                $table->dropColumn('deduction_amount');
            }
            if (Schema::hasColumn('invoice_payments', 'tds_amount')) {
                $table->dropColumn('tds_amount');
            }
        });
    }
};
