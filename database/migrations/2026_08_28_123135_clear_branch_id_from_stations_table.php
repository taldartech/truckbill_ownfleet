<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stations') || ! Schema::hasColumn('stations', 'branch_id')) {
            return;
        }

        DB::table('stations')
            ->whereNotNull('branch_id')
            ->update(['branch_id' => null]);
    }

    public function down(): void
    {
        // Branch assignment for stations is no longer used.
    }
};
