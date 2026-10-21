<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->char('deleted_by', 36)
                ->nullable()
                ->after('updated_by');

            $table->text('deleted_reason')
                ->nullable()
                ->after('deleted_by');

            $table->foreign('deleted_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn([
                'deleted_by',
                'deleted_reason',
            ]);
        });
    }
};