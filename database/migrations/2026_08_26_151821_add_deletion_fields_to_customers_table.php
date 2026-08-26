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
            $table->uuid('deleted_by')
                ->nullable()
                ->change();

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
            $table->dropColumn('deleted_reason');

            $table->unsignedBigInteger('deleted_by')
                ->nullable()
                ->change();
        });
    }
};