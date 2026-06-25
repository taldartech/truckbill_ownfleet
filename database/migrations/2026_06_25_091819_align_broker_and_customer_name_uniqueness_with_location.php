<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const TABLES = ['brokers', 'customers'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $locationUnique = "{$tableName}_tenant_id_state_id_city_id_name_unique";
            $legacyUnique = "{$tableName}_tenant_id_name_unique";

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $locationUnique): void {
                $indexes = Schema::getIndexes($tableName);
                $indexNames = array_column($indexes, 'name');

                if (! in_array($locationUnique, $indexNames, true)) {
                    $table->unique(
                        ['tenant_id', 'state_id', 'city_id', 'name'],
                        $locationUnique,
                    );
                }
            });

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $legacyUnique): void {
                $indexes = Schema::getIndexes($tableName);
                $indexNames = array_column($indexes, 'name');

                if (in_array($legacyUnique, $indexNames, true)) {
                    $table->dropUnique($legacyUnique);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $locationUnique = "{$tableName}_tenant_id_state_id_city_id_name_unique";
            $legacyUnique = "{$tableName}_tenant_id_name_unique";

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $legacyUnique): void {
                $indexes = Schema::getIndexes($tableName);
                $indexNames = array_column($indexes, 'name');

                if (! in_array($legacyUnique, $indexNames, true)) {
                    $table->unique(['tenant_id', 'name'], $legacyUnique);
                }
            });

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $locationUnique): void {
                $indexes = Schema::getIndexes($tableName);
                $indexNames = array_column($indexes, 'name');

                if (in_array($locationUnique, $indexNames, true)) {
                    $table->dropUnique($locationUnique);
                }
            });
        }
    }
};
