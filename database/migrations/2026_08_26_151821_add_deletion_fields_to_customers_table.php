<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignUuid('deleted_by')
                ->nullable()
                ->after('deleted_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->text('deleted_reason')
                ->nullable()
                ->after('deleted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn('deleted_reason');
        });
    }
};