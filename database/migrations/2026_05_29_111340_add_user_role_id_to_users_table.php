<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, int>
     */
    private const LEGACY_ROLE_MAP = [
        'master_admin' => 1,
        'super_admin' => 2,
        'admin' => 3,
        'moderator' => 4,
        'accountant' => 5,
        'transporter' => 6,
        'customer' => 7,
        'fleet_manager' => 3,
        'driver' => 7,
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('users', 'user_role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('user_role_id')
                    ->nullable()
                    ->after('password')
                    ->constrained('user_roles');
            });
        }

        if (Schema::hasColumn('users', 'role')) {
            foreach (DB::table('users')->select('id', 'role')->get() as $user) {
                $normalized = strtolower(str_replace('-', '_', (string) $user->role));
                $roleId = self::LEGACY_ROLE_MAP[$normalized] ?? 3;

                DB::table('users')->where('id', $user->id)->update([
                    'user_role_id' => $roleId,
                ]);
            }

            if ($this->indexExists('users', 'users_tenant_id_role_index')) {
                if ($this->foreignKeyExists('users', 'users_tenant_id_foreign')) {
                    Schema::table('users', function (Blueprint $table) {
                        $table->dropForeign(['tenant_id']);
                    });
                }

                Schema::table('users', function (Blueprint $table) {
                    $table->dropIndex(['tenant_id', 'role']);
                });
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }

        if (Schema::hasColumn('users', 'user_role_id')) {
            DB::table('users')->whereNull('user_role_id')->update(['user_role_id' => 3]);

            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('user_role_id')->nullable(false)->change();
            });
        }

        if (! $this->indexExists('users', 'users_tenant_id_user_role_id_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['tenant_id', 'user_role_id']);
            });
        }

        if (
            Schema::hasColumn('users', 'tenant_id')
            && ! $this->foreignKeyExists('users', 'users_tenant_id_foreign')
        ) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('users', 'users_tenant_id_user_role_id_index')) {
            if ($this->foreignKeyExists('users', 'users_tenant_id_foreign')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropForeign(['tenant_id']);
                });
            }

            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'user_role_id']);
            });
        }

        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('admin')->after('password');
            });

            $roleNames = DB::table('user_roles')->pluck('name', 'id');

            foreach (DB::table('users')->select('id', 'user_role_id')->get() as $user) {
                DB::table('users')->where('id', $user->id)->update([
                    'role' => strtoupper((string) ($roleNames[$user->user_role_id] ?? 'admin')),
                ]);
            }
        }

        if (Schema::hasColumn('users', 'user_role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['user_role_id']);
                $table->dropColumn('user_role_id');
            });
        }

        if (! $this->indexExists('users', 'users_tenant_id_role_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['tenant_id', 'role']);
            });
        }

        if (
            Schema::hasColumn('users', 'tenant_id')
            && ! $this->foreignKeyExists('users', 'users_tenant_id_foreign')
        ) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }
    }

    private function foreignKeyExists(string $table, string $foreignKeyName): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $foreignKeys = Schema::getConnection()
                ->getSchemaBuilder()
                ->getForeignKeys($table);

            return collect($foreignKeys)
                ->contains(static fn (array $foreignKey): bool => ($foreignKey['name'] ?? '') === $foreignKeyName);
        }

        $database = Schema::getConnection()->getDatabaseName();

        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$database, $table, $foreignKeyName, 'FOREIGN KEY']
        );

        return $constraints !== [];
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return collect(Schema::getIndexes($table))
                ->contains(static fn (array $index): bool => ($index['name'] ?? '') === $indexName);
        }

        $indexes = DB::select('SHOW INDEX FROM '.$table.' WHERE Key_name = ?', [$indexName]);

        return $indexes !== [];
    }
};
