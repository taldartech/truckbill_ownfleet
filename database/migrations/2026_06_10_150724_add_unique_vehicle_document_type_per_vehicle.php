<?php

use App\VehicleDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateKeys = DB::table('vehicle_documents')
            ->select('vehicle_id', 'document_type')
            ->groupBy('vehicle_id', 'document_type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateKeys as $key) {
            $documents = VehicleDocument::query()
                ->withoutGlobalScopes()
                ->where('vehicle_id', $key->vehicle_id)
                ->where('document_type', $key->document_type)
                ->orderByDesc('expiry_date')
                ->orderByDesc('updated_at')
                ->get();

            $documents->slice(1)->each->delete();
        }

        Schema::table('vehicle_documents', function (Blueprint $table) {
            $table->unique(['vehicle_id', 'document_type'], 'vehicle_documents_vehicle_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_documents', function (Blueprint $table) {
            $table->dropUnique('vehicle_documents_vehicle_type_unique');
        });
    }
};
