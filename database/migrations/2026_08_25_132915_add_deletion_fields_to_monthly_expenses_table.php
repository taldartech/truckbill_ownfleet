<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_expenses', function (Blueprint $table) {
            $table->text('deleted_reason')->nullable()->after('amount');
            $table->foreignUuid('deleted_by')->nullable()->after('deleted_reason');

            $table->index('deleted_by');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_expenses', function (Blueprint $table) {
            $table->dropIndex(['deleted_by']);

            $table->dropColumn([
                'deleted_reason',
                'deleted_by',
            ]);
        });
    }
};