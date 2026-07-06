<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tyres')
            ->whereNotNull('warranty_details')
            ->where('warranty_details', '!=', '')
            ->orderBy('id')
            ->each(function (object $tyre): void {
                $warrantyDetails = trim((string) $tyre->warranty_details);
                $existingNotes = trim((string) ($tyre->notes ?? ''));

                if ($warrantyDetails === '') {
                    return;
                }

                $mergedNotes = $existingNotes === ''
                    ? $warrantyDetails
                    : $existingNotes."\n\n".$warrantyDetails;

                DB::table('tyres')
                    ->where('id', $tyre->id)
                    ->update(['notes' => $mergedNotes]);
            });

        Schema::table('tyres', function (Blueprint $table): void {
            $table->dropColumn('warranty_details');
        });
    }

    public function down(): void
    {
        Schema::table('tyres', function (Blueprint $table): void {
            $table->text('warranty_details')->nullable()->after('purchase_date');
        });
    }
};
