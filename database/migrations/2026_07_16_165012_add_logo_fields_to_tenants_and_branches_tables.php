<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('company_logo')->nullable()->after('contact_number');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('use_company_logo')->default(true)->after('gst_rate');
            $table->string('branch_logo')->nullable()->after('use_company_logo');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('company_logo');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['use_company_logo', 'branch_logo']);
        });
    }
};
