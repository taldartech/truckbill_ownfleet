<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            $sm = Schema::getConnection()->getSchemaBuilder();

            if (method_exists($sm, 'hasIndex') && $sm->hasIndex('branches', 'branches_branch_contact_number_unique')) {
                $table->dropUnique(['branch_contact_number']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            $sm = Schema::getConnection()->getSchemaBuilder();

            if (method_exists($sm, 'hasIndex') && ! $sm->hasIndex('branches', 'branches_branch_contact_number_unique')) {
                $table->unique('branch_contact_number');
            }
        });
    }
};
