<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_UNIQUE_INDEX = 'challans_tenant_id_challan_number_unique';

    private const SOFT_DELETE_UNIQUE_INDEX = 'challans_tenant_id_challan_number_deleted_at_unique';

    public function up(): void
    {
        if ($this->indexExists('challans', self::LEGACY_UNIQUE_INDEX)) {
            Schema::table('challans', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'challan_number']);
            });
        }

        if (! Schema::hasColumn('challans', 'deleted_at')) {
            Schema::table('challans', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (! $this->indexExists('challans', self::SOFT_DELETE_UNIQUE_INDEX)) {
            Schema::table('challans', function (Blueprint $table) {
                $table->unique(['tenant_id', 'challan_number', 'deleted_at']);
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('challans', self::SOFT_DELETE_UNIQUE_INDEX)) {
            Schema::table('challans', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'challan_number', 'deleted_at']);
            });
        }

        if (Schema::hasColumn('challans', 'deleted_at')) {
            Schema::table('challans', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (! $this->indexExists('challans', self::LEGACY_UNIQUE_INDEX)) {
            Schema::table('challans', function (Blueprint $table) {
                $table->unique(['tenant_id', 'challan_number']);
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = Schema::getConnection()->getSchemaBuilder()->getIndexes($table);

            foreach ($indexes as $index) {
                if (($index['name'] ?? '') === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $indexes = DB::select('SHOW INDEX FROM '.$table.' WHERE Key_name = ?', [$indexName]);

        return $indexes !== [];
    }
};
