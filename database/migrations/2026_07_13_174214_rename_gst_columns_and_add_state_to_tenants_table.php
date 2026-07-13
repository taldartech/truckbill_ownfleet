<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tenants', 'gst_scheme')) {
            DB::table('tenants')
                ->where('gst_scheme', 'forward charge')
                ->update(['gst_scheme' => 'forward_charge']);

            DB::table('tenants')
                ->where('gst_scheme', 'reverse charge')
                ->update(['gst_scheme' => 'reverse_charge']);

            Schema::table('tenants', function (Blueprint $table) {
                $table->renameColumn('gst_scheme', 'tax_liability');
            });
        }

        if (Schema::hasColumn('tenants', 'gst_type')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->renameColumn('gst_type', 'registration_type');
            });
        }

        if (! Schema::hasColumn('tenants', 'state_id')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->foreignId('state_id')->nullable()->after('pan_number')->constrained('states')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('tenants', 'gst_rate')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->decimal('gst_rate', 8, 3)->nullable()->after('state_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'gst_rate')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('gst_rate');
            });
        }

        if (Schema::hasColumn('tenants', 'state_id')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropConstrainedForeignId('state_id');
            });
        }

        if (Schema::hasColumn('tenants', 'registration_type')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->renameColumn('registration_type', 'gst_type');
            });
        }

        if (Schema::hasColumn('tenants', 'tax_liability')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->renameColumn('tax_liability', 'gst_scheme');
            });

            DB::table('tenants')
                ->where('gst_scheme', 'forward_charge')
                ->update(['gst_scheme' => 'forward charge']);

            DB::table('tenants')
                ->where('gst_scheme', 'reverse_charge')
                ->update(['gst_scheme' => 'reverse charge']);
        }
    }
};
