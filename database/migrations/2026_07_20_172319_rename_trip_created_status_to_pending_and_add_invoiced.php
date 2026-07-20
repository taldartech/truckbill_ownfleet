<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('trips')
            ->where('status', 'CREATED')
            ->update(['status' => 'PENDING']);

        if (Schema::hasTable('trip_status_histories')) {
            DB::table('trip_status_histories')
                ->where('from_status', 'CREATED')
                ->update(['from_status' => 'PENDING']);

            DB::table('trip_status_histories')
                ->where('to_status', 'CREATED')
                ->update(['to_status' => 'PENDING']);
        }

        Schema::table('trips', function (Blueprint $table): void {
            $table->string('status')->default('PENDING')->change();
        });
    }

    public function down(): void
    {
        DB::table('trips')
            ->where('status', 'PENDING')
            ->update(['status' => 'CREATED']);

        DB::table('trips')
            ->where('status', 'INVOICED')
            ->update(['status' => 'COMPLETED']);

        if (Schema::hasTable('trip_status_histories')) {
            DB::table('trip_status_histories')
                ->where('from_status', 'PENDING')
                ->update(['from_status' => 'CREATED']);

            DB::table('trip_status_histories')
                ->where('to_status', 'PENDING')
                ->update(['to_status' => 'CREATED']);

            DB::table('trip_status_histories')
                ->where('from_status', 'INVOICED')
                ->update(['from_status' => 'COMPLETED']);

            DB::table('trip_status_histories')
                ->where('to_status', 'INVOICED')
                ->update(['to_status' => 'COMPLETED']);
        }

        Schema::table('trips', function (Blueprint $table): void {
            $table->string('status')->default('CREATED')->change();
        });
    }
};
