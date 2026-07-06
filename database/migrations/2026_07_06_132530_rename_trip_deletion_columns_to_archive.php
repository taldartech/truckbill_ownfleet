<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trips')) {
            return;
        }

        if (Schema::hasColumn('trips', 'deleted_by') && ! Schema::hasColumn('trips', 'archived_by')) {
            $this->dropForeignKeyIfExists('trips', 'deleted_by');

            Schema::table('trips', function (Blueprint $table): void {
                $table->renameColumn('deleted_by', 'archived_by');
            });

            Schema::table('trips', function (Blueprint $table): void {
                $table->foreign('archived_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('trips', 'deletion_reason') && ! Schema::hasColumn('trips', 'archive_reason')) {
            Schema::table('trips', function (Blueprint $table): void {
                $table->renameColumn('deletion_reason', 'archive_reason');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('trips')) {
            return;
        }

        if (Schema::hasColumn('trips', 'archive_reason') && ! Schema::hasColumn('trips', 'deletion_reason')) {
            Schema::table('trips', function (Blueprint $table): void {
                $table->renameColumn('archive_reason', 'deletion_reason');
            });
        }

        if (Schema::hasColumn('trips', 'archived_by') && ! Schema::hasColumn('trips', 'deleted_by')) {
            $this->dropForeignKeyIfExists('trips', 'archived_by');

            Schema::table('trips', function (Blueprint $table): void {
                $table->renameColumn('archived_by', 'deleted_by');
            });

            Schema::table('trips', function (Blueprint $table): void {
                $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->dropForeign([$column]);
            });
        } catch (\Throwable) {
            // Foreign key may not exist in all environments.
        }
    }
};
