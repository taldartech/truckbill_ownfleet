<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyStatuses = ['DAMAGED', 'RETREADED'];

        $installedTyreIds = DB::table('tyre_installations')
            ->whereNull('removed_at')
            ->pluck('tyre_id');

        DB::table('tyres')
            ->whereIn('status', $legacyStatuses)
            ->whereIn('id', $installedTyreIds)
            ->update(['status' => 'INSTALLED']);

        DB::table('tyres')
            ->whereIn('status', $legacyStatuses)
            ->update(['status' => 'IN_STOCK']);
    }

    public function down(): void
    {
        // Legacy statuses are no longer supported.
    }
};
