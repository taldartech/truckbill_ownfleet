<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batteries', function (Blueprint $table): void {
            $table->text('scrap_notes')->nullable()->after('scrap_sale_amount');
        });
    }

    public function down(): void
    {
        Schema::table('batteries', function (Blueprint $table): void {
            $table->dropColumn('scrap_notes');
        });
    }
};
