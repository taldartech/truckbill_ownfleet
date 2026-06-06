<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dateTime('cancelled_at')->nullable()->after('end_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            $table->dateTime('actual_arrival_at')->nullable()->after('cancellation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancellation_reason', 'actual_arrival_at']);
        });
    }
};
