<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('inherit_company_gst')->default(true)->after('trip_sequence_start');
            $table->string('registration_type')->nullable()->after('inherit_company_gst');
            $table->string('tax_liability')->nullable()->after('registration_type');
            $table->string('gst_number')->nullable()->after('tax_liability');
            $table->unsignedBigInteger('state_id')->nullable()->after('gst_number');
            $table->decimal('gst_rate', 8, 3)->nullable()->after('state_id');
            $table->foreign('state_id')->references('id')->on('states')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['state_id']);
            $table->dropColumn([
                'inherit_company_gst',
                'registration_type',
                'tax_liability',
                'gst_number',
                'state_id',
                'gst_rate',
            ]);
        });
    }
};
