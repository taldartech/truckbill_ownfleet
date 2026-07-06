<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYRE_ACTIVE_TYRE_INDEX = 'tyre_installations_active_tyre_unique';

    public function up(): void
    {
        $this->addCreatedByColumns();
        $this->createTyreAuditLogsTable();
        $this->createTyreInspectionsTable();
        $this->addTyreActiveTyreUniqueIndex();
    }

    public function down(): void
    {
        $this->dropTyreActiveTyreUniqueIndex();
        Schema::dropIfExists('tyre_inspections');
        Schema::dropIfExists('tyre_audit_logs');
        $this->dropCreatedByColumns();
    }

    private function addCreatedByColumns(): void
    {
        $tables = [
            'tyre_installations' => ['created_by', 'removed_by'],
            'tyre_rotations' => ['created_by'],
            'tyre_damages' => ['created_by'],
            'tyre_replacements' => ['created_by'],
            'tyre_expenses' => ['created_by'],
        ];

        foreach ($tables as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    if (! Schema::hasColumn($blueprint->getTable(), $column)) {
                        $blueprint->foreignUuid($column)->nullable()->constrained('users')->nullOnDelete();
                    }
                }
            });
        }

        if (Schema::hasTable('tyres') && ! Schema::hasColumn('tyres', 'last_inspection_at')) {
            Schema::table('tyres', function (Blueprint $table): void {
                $table->date('last_inspection_at')->nullable()->after('warranty_km');
                $table->decimal('last_tread_depth_mm', 5, 2)->nullable()->after('last_inspection_at');
            });
        }
    }

    private function dropCreatedByColumns(): void
    {
        if (Schema::hasTable('tyres') && Schema::hasColumn('tyres', 'last_inspection_at')) {
            Schema::table('tyres', function (Blueprint $table): void {
                $table->dropColumn(['last_inspection_at', 'last_tread_depth_mm']);
            });
        }

        $tables = [
            'tyre_installations' => ['created_by', 'removed_by'],
            'tyre_rotations' => ['created_by'],
            'tyre_damages' => ['created_by'],
            'tyre_replacements' => ['created_by'],
            'tyre_expenses' => ['created_by'],
        ];

        foreach ($tables as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($blueprint->getTable(), $column)) {
                        $blueprint->dropConstrainedForeignId($column);
                    }
                }
            });
        }
    }

    private function createTyreAuditLogsTable(): void
    {
        if (Schema::hasTable('tyre_audit_logs')) {
            return;
        }

        Schema::create('tyre_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('tyre_id')->nullable()->constrained('tyres')->nullOnDelete();
            $table->foreignUuid('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('event_type', 64);
            $table->string('subject_type', 128)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->json('payload')->nullable();
            $table->foreignUuid('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->timestamps();

            $table->index(['tyre_id', 'performed_at']);
            $table->index(['vehicle_id', 'performed_at']);
        });
    }

    private function createTyreInspectionsTable(): void
    {
        if (Schema::hasTable('tyre_inspections')) {
            return;
        }

        Schema::create('tyre_inspections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('tyre_id')->constrained('tyres')->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->date('inspected_at');
            $table->decimal('tread_depth_mm', 5, 2)->nullable();
            $table->decimal('pressure_psi', 6, 2)->nullable();
            $table->string('result', 32)->default('PASS');
            $table->date('next_due_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tyre_id', 'inspected_at']);
            $table->index(['next_due_at']);
        });
    }

    private function addTyreActiveTyreUniqueIndex(): void
    {
        if ($this->indexExists('tyre_installations', self::TYRE_ACTIVE_TYRE_INDEX)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement(
                'CREATE UNIQUE INDEX '.self::TYRE_ACTIVE_TYRE_INDEX.'
                 ON tyre_installations (tyre_id)
                 WHERE removed_at IS NULL',
            );

            return;
        }

        if ($driver === 'mysql') {
            if ($this->isMariaDb()) {
                if (! Schema::hasColumn('tyre_installations', 'active_tyre_key')) {
                    DB::statement(
                        'ALTER TABLE tyre_installations
                         ADD COLUMN active_tyre_key CHAR(36)
                         AS (IF(removed_at IS NULL, tyre_id, NULL)) VIRTUAL',
                    );
                }

                DB::statement(
                    'CREATE UNIQUE INDEX '.self::TYRE_ACTIVE_TYRE_INDEX.'
                     ON tyre_installations (active_tyre_key)',
                );

                return;
            }

            DB::statement(
                'CREATE UNIQUE INDEX '.self::TYRE_ACTIVE_TYRE_INDEX.'
                 ON tyre_installations ((IF(removed_at IS NULL, tyre_id, NULL)))',
            );
        }
    }

    private function dropTyreActiveTyreUniqueIndex(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS '.self::TYRE_ACTIVE_TYRE_INDEX);

            return;
        }

        if ($driver === 'mysql') {
            if ($this->indexExists('tyre_installations', self::TYRE_ACTIVE_TYRE_INDEX)) {
                DB::statement('DROP INDEX '.self::TYRE_ACTIVE_TYRE_INDEX.' ON tyre_installations');
            }

            if ($this->isMariaDb() && Schema::hasColumn('tyre_installations', 'active_tyre_key')) {
                DB::statement('ALTER TABLE tyre_installations DROP COLUMN active_tyre_key');
            }
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            return collect($indexes)->contains(fn ($row) => ($row->name ?? '') === $indexName);
        }

        if ($driver === 'mysql') {
            $database = DB::getDatabaseName();
            $result = DB::select(
                'SELECT 1 FROM information_schema.statistics
                 WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [$database, $table, $indexName],
            );

            return $result !== [];
        }

        return false;
    }

    private function isMariaDb(): bool
    {
        $version = DB::selectOne('SELECT VERSION() AS version');

        return str_contains(strtolower((string) ($version->version ?? '')), 'mariadb');
    }
};
