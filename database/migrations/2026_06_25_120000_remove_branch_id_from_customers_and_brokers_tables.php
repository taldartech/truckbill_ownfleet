<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const TABLES = ['customers', 'brokers'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'branch_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'branch_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('tenant_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            });
        }
    }
};
