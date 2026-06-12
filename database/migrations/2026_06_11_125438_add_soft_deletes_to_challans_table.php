<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_UNIQUE_INDEX = 'challans_tenant_id_challan_number_unique';

    private const SOFT_DELETE_UNIQUE_INDEX = 'challans_tenant_id_challan_number_deleted_at_unique';

    private const TENANT_ID_INDEX = 'challans_tenant_id_index';

    public function up(): void
    {
        if ($this->indexExists('challans', self::LEGACY_UNIQUE_INDEX)) {
            if (! $this->hasStandaloneTenantIdIndex('challans')) {
                Schema::table('challans', function (Blueprint $table) {
                    $table->index('tenant_id', self::TENANT_ID_INDEX);
                });
            }

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

        if ($this->indexExists('challans', self::TENANT_ID_INDEX)) {
            Schema::table('challans', function (Blueprint $table) {
                $table->dropIndex(self::TENANT_ID_INDEX);
            });
        }
    }

    private function hasStandaloneTenantIdIndex(string $table): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = Schema::getConnection()->getSchemaBuilder()->getIndexes($table);

            foreach ($indexes as $index) {
                $columns = $index['columns'] ?? [];

                if ($columns === ['tenant_id']) {
                    return true;
                }
            }

            return false;
        }

        $database = Schema::getConnection()->getDatabaseName();

        $indexes = DB::select(
            'SELECT INDEX_NAME, COUNT(*) AS column_count
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
             GROUP BY INDEX_NAME',
            [$database, $table],
        );

        foreach ($indexes as $index) {
            if ((int) $index->column_count !== 1) {
                continue;
            }

            $column = DB::selectOne(
                'SELECT COLUMN_NAME
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = ?
                   AND TABLE_NAME = ?
                   AND INDEX_NAME = ?
                   AND SEQ_IN_INDEX = 1
                 LIMIT 1',
                [$database, $table, $index->INDEX_NAME],
            );

            if ($column?->COLUMN_NAME === 'tenant_id') {
                return true;
            }
        }

        return false;
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
