<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('challans', function (Blueprint $table) {
            $table->foreignUuid('driver_id')->nullable()->after('vehicle_id')->constrained('drivers')->nullOnDelete();
            $table->string('violation_type')->nullable()->after('challan_date');
        });

        DB::table('challans')->where('status', 'PENDING')->update(['status' => 'UNPAID']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('challans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_id');
            $table->dropColumn('violation_type');
        });
    }
};
