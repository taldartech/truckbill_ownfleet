<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignUuid('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['invoice_id', 'trip_id']);
            $table->index(['trip_id', 'invoice_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignUuid('trip_id')->nullable()->change();
            $table->foreign('trip_id')->references('id')->on('trips')->nullOnDelete();
        });

        $rows = DB::table('invoices')
            ->whereNotNull('trip_id')
            ->select(['id', 'trip_id', 'created_at', 'updated_at'])
            ->get();

        foreach ($rows as $row) {
            DB::table('invoice_trips')->insertOrIgnore([
                'invoice_id' => $row->id,
                'trip_id' => $row->trip_id,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_trips');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignUuid('trip_id')->nullable(false)->change();
            $table->foreign('trip_id')->references('id')->on('trips')->cascadeOnDelete();
        });
    }
};
