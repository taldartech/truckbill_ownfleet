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
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $tenantIndex = "{$tableName}_tenant_id_index";
            $oldNameLocationUnique = "{$tableName}_tenant_id_state_id_city_id_name_unique";
            $mobileUnique = "{$tableName}_tenant_id_contact_number_deleted_at_unique";

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $tenantIndex, $oldNameLocationUnique, $mobileUnique): void {
                $indexes = Schema::getIndexes($tableName);
                $indexNames = array_column($indexes, 'name');

                // 1. Ensure tenant_id index exists to support FK constraint
                if (! in_array($tenantIndex, $indexNames, true)) {
                    $table->index('tenant_id', $tenantIndex);
                }

                // 2. Drop name + city uniqueness (allow duplicate names in same city)
                if (in_array($oldNameLocationUnique, $indexNames, true)) {
                    $table->dropUnique($oldNameLocationUnique);
                }

                // 3. Enforce mobile number uniqueness per tenant
                if (! in_array($mobileUnique, $indexNames, true)) {
                    $table->unique(['tenant_id', 'contact_number', 'deleted_at'], $mobileUnique);
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

            $tenantIndex = "{$tableName}_tenant_id_index";
            $oldNameLocationUnique = "{$tableName}_tenant_id_state_id_city_id_name_unique";
            $mobileUnique = "{$tableName}_tenant_id_contact_number_deleted_at_unique";

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $tenantIndex, $oldNameLocationUnique, $mobileUnique): void {
                $indexes = Schema::getIndexes($tableName);
                $indexNames = array_column($indexes, 'name');

                if (in_array($mobileUnique, $indexNames, true)) {
                    $table->dropUnique($mobileUnique);
                }

                if (! in_array($oldNameLocationUnique, $indexNames, true)) {
                    $table->unique(['tenant_id', 'state_id', 'city_id', 'name'], $oldNameLocationUnique);
                }

                if (in_array($tenantIndex, $indexNames, true)) {
                    $table->dropIndex($tenantIndex);
                }
            });
        }
    }
};
