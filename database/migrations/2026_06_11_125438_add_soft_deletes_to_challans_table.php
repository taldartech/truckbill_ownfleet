<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challans', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'challan_number']);
            $table->softDeletes();
            $table->unique(['tenant_id', 'challan_number', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('challans', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'challan_number', 'deleted_at']);
            $table->dropSoftDeletes();
            $table->unique(['tenant_id', 'challan_number']);
        });
    }
};
