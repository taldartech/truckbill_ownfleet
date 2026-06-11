<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tyre_installations', function (Blueprint $table) {
            $table->decimal('stint_km', 12, 2)->default(0)->after('km_at_removal');
        });
    }

    public function down(): void
    {
        Schema::table('tyre_installations', function (Blueprint $table) {
            $table->dropColumn('stint_km');
        });
    }
};
