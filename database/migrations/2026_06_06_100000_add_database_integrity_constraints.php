<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TYRE_ACTIVE_POSITION_INDEX = 'tyre_installations_vehicle_active_position_unique';

    public function up(): void
    {
        if ($this->foreignKeyExists('trips', 'vehicle_id')) {
            Schema::table('trips', function (Blueprint $table) {
                $table->dropForeign(['vehicle_id']);
                $table->dropForeign(['driver_id']);
            });
        }

        Schema::table('trips', function (Blueprint $table) {
            if (! $this->foreignKeyExists('trips', 'vehicle_id')) {
                $table->foreign('vehicle_id')->references('id')->on('vehicles')->restrictOnDelete();
            }

            if (! $this->foreignKeyExists('trips', 'driver_id')) {
                $table->foreign('driver_id')->references('id')->on('drivers')->restrictOnDelete();
            }

            if (! $this->foreignKeyExists('trips', 'start_station_id')) {
                $table->foreign('start_station_id')->references('id')->on('stations')->nullOnDelete();
            }
        });

        Schema::table('trip_route_legs', function (Blueprint $table) {
            if (! $this->indexExists('trip_route_legs', 'trip_route_legs_trip_id_sequence_no_unique')) {
                $table->unique(['trip_id', 'sequence_no']);
            }

            if (! $this->foreignKeyExists('trip_route_legs', 'from_station_id')) {
                $table->foreign('from_station_id')->references('id')->on('stations')->nullOnDelete();
            }

            if (! $this->foreignKeyExists('trip_route_legs', 'to_station_id')) {
                $table->foreign('to_station_id')->references('id')->on('stations')->nullOnDelete();
            }
        });

        if ($this->foreignKeyExists('challans', 'vehicle_id')) {
            Schema::table('challans', function (Blueprint $table) {
                $table->dropForeign(['vehicle_id']);
            });
        }

        Schema::table('challans', function (Blueprint $table) {
            if (! $this->foreignKeyExists('challans', 'vehicle_id')) {
                $table->foreign('vehicle_id')->references('id')->on('vehicles')->restrictOnDelete();
            }
        });

        $this->addTyreActivePositionUniqueIndex();
    }

    public function down(): void
    {
        $this->dropTyreActivePositionUniqueIndex();

        if ($this->foreignKeyExists('challans', 'vehicle_id')) {
            Schema::table('challans', function (Blueprint $table) {
                $table->dropForeign(['vehicle_id']);
            });
        }

        Schema::table('challans', function (Blueprint $table) {
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnDelete();
        });

        Schema::table('trip_route_legs', function (Blueprint $table) {
            if ($this->foreignKeyExists('trip_route_legs', 'from_station_id')) {
                $table->dropForeign(['from_station_id']);
            }

            if ($this->foreignKeyExists('trip_route_legs', 'to_station_id')) {
                $table->dropForeign(['to_station_id']);
            }

            if ($this->indexExists('trip_route_legs', 'trip_route_legs_trip_id_sequence_no_unique')) {
                $table->dropUnique(['trip_id', 'sequence_no']);
            }
        });

        Schema::table('trips', function (Blueprint $table) {
            if ($this->foreignKeyExists('trips', 'start_station_id')) {
                $table->dropForeign(['start_station_id']);
            }

            if ($this->foreignKeyExists('trips', 'vehicle_id')) {
                $table->dropForeign(['vehicle_id']);
            }

            if ($this->foreignKeyExists('trips', 'driver_id')) {
                $table->dropForeign(['driver_id']);
            }
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnDelete();
            $table->foreign('driver_id')->references('id')->on('drivers')->cascadeOnDelete();
        });
    }

    private function addTyreActivePositionUniqueIndex(): void
    {
        if ($this->indexExists('tyre_installations', self::TYRE_ACTIVE_POSITION_INDEX)
            || $this->indexExists('tyre_installations', 'tyre_installations_active_position_key_unique')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement(
                'CREATE UNIQUE INDEX '.self::TYRE_ACTIVE_POSITION_INDEX.'
                 ON tyre_installations (vehicle_id, position_code)
                 WHERE removed_at IS NULL',
            );

            return;
        }

        if ($driver === 'mysql') {
            $this->closeDuplicateActiveTyrePositions();

            DB::statement(
                'CREATE UNIQUE INDEX '.self::TYRE_ACTIVE_POSITION_INDEX.'
                 ON tyre_installations ((IF(removed_at IS NULL, CONCAT(vehicle_id, CHAR(124), position_code), NULL)))',
            );
        }
    }

    private function dropTyreActivePositionUniqueIndex(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS '.self::TYRE_ACTIVE_POSITION_INDEX);

            return;
        }

        if ($driver === 'mysql') {
            if ($this->indexExists('tyre_installations', self::TYRE_ACTIVE_POSITION_INDEX)) {
                DB::statement('DROP INDEX '.self::TYRE_ACTIVE_POSITION_INDEX.' ON tyre_installations');
            }

            if ($this->indexExists('tyre_installations', 'tyre_installations_active_position_key_unique')) {
                DB::statement('DROP INDEX tyre_installations_active_position_key_unique ON tyre_installations');
            }

            if (Schema::hasColumn('tyre_installations', 'active_position_key')) {
                DB::statement('ALTER TABLE tyre_installations DROP COLUMN active_position_key');
            }
        }
    }

    private function closeDuplicateActiveTyrePositions(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            'UPDATE tyre_installations AS ti
             INNER JOIN (
                 SELECT vehicle_id, position_code, MIN(id) AS keep_id
                 FROM tyre_installations
                 WHERE removed_at IS NULL
                 GROUP BY vehicle_id, position_code
                 HAVING COUNT(*) > 1
             ) AS duplicates
                 ON ti.vehicle_id = duplicates.vehicle_id
                 AND ti.position_code = duplicates.position_code
                 AND ti.removed_at IS NULL
                 AND ti.id <> duplicates.keep_id
             SET ti.removed_at = CURDATE()',
        );
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $foreignKeys = Schema::getConnection()->getSchemaBuilder()->getForeignKeys($table);

            foreach ($foreignKeys as $foreignKey) {
                if (in_array($column, $foreignKey['columns'], true)) {
                    return true;
                }
            }

            return false;
        }

        $database = Schema::getConnection()->getDatabaseName();

        $result = DB::select(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1',
            [$database, $table, $column],
        );

        return $result !== [];
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

        $database = Schema::getConnection()->getDatabaseName();

        $result = DB::select(
            'SELECT INDEX_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?
             LIMIT 1',
            [$database, $table, $indexName],
        );

        return $result !== [];
    }
};
