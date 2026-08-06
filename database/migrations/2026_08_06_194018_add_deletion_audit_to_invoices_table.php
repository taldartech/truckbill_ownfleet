<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'deleted_by')) {
                $table->foreignUuid('deleted_by')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('invoices', 'deletion_reason')) {
                $table->text('deletion_reason')->nullable()->after('deleted_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'deletion_reason')) {
                $table->dropColumn('deletion_reason');
            }

            if (Schema::hasColumn('invoices', 'deleted_by')) {
                $table->dropConstrainedForeignId('deleted_by');
            }
        });
    }
};
