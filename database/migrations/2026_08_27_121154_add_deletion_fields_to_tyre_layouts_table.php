<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tyre_layouts', function (Blueprint $table) {
            $table->foreignUuid('deleted_by')
                ->nullable()
                ->after('is_active')
                ->constrained('users')
                ->nullOnDelete();

            $table->text('deleted_reason')
                ->nullable()
                ->after('deleted_by');
        });
    }

    public function down(): void
    {
        Schema::table('tyre_layouts', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);

            $table->dropColumn([
                'deleted_by',
                'deleted_reason',
            ]);
        });
    }
};