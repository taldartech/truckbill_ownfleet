<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tyres', function (Blueprint $table) {
            $table->date('warranty_valid_until')->nullable()->after('warranty_details');
            $table->decimal('warranty_km', 12, 2)->nullable()->after('warranty_valid_until');
        });
    }

    public function down(): void
    {
        Schema::table('tyres', function (Blueprint $table) {
            $table->dropColumn(['warranty_valid_until', 'warranty_km']);
        });
    }
};
