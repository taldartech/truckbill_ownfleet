<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('branches')
            ->where('trip_sequence_start', 0)
            ->update(['trip_sequence_start' => 1]);
    }

    public function down(): void
    {
        // No rollback needed.
    }
};
