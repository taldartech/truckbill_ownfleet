<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            if (! Schema::hasColumn('trips', 'notes')) {
                $table->text('notes')->nullable()->after('cancellation_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            if (Schema::hasColumn('trips', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
