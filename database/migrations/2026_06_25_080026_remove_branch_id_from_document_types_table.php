<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('document_types') || ! Schema::hasColumn('document_types', 'branch_id')) {
            return;
        }

        Schema::table('document_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('document_types') || Schema::hasColumn('document_types', 'branch_id')) {
            return;
        }

        Schema::table('document_types', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('branches')
                ->nullOnDelete();
        });
    }
};
