<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_expenses', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('notes');
            $table->index(['tenant_id', 'reference'], 'trip_expenses_tenant_reference_index');
        });
    }

    public function down(): void
    {
        Schema::table('trip_expenses', function (Blueprint $table) {
            $table->dropIndex('trip_expenses_tenant_reference_index');
            $table->dropColumn('reference');
        });
    }
};
