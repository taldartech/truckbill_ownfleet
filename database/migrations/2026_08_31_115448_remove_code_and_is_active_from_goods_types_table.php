<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_types', function (Blueprint $table) {
            $table->dropUnique('goods_types_tenant_id_code_unique');
            $table->dropColumn([
                'code',
                'is_active',
            ]);
        });

        Schema::table('goods_types', function (Blueprint $table) {
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('goods_types', function (Blueprint $table) {
            $table->dropUnique('goods_types_tenant_id_name_unique');

            $table->string('code', 64)->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::table('goods_types', function (Blueprint $table) {
            $table->unique(['tenant_id', 'code']);
        });
    }
};