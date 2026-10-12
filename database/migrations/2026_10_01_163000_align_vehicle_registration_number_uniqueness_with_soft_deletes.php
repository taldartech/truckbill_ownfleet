<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_UNIQUE_INDEX = 'vehicles_tenant_id_registration_number_unique';

    private const SOFT_DELETE_UNIQUE_INDEX = 'vehicles_tenant_id_registration_number_deleted_at_unique';

    public function up(): void
    {
        $indexes = array_column(Schema::getIndexes('vehicles'), 'name');

        if (in_array(self::LEGACY_UNIQUE_INDEX, $indexes, true)) {
            Schema::table('vehicles', function (Blueprint $table): void {
                $table->dropUnique(self::LEGACY_UNIQUE_INDEX);
            });
        }

        $indexes = array_column(Schema::getIndexes('vehicles'), 'name');

        if (! in_array(self::SOFT_DELETE_UNIQUE_INDEX, $indexes, true)) {
            Schema::table('vehicles', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'registration_number', 'deleted_at'], self::SOFT_DELETE_UNIQUE_INDEX);
            });
        }
    }

    public function down(): void
    {
        $indexes = array_column(Schema::getIndexes('vehicles'), 'name');

        if (in_array(self::SOFT_DELETE_UNIQUE_INDEX, $indexes, true)) {
            Schema::table('vehicles', function (Blueprint $table): void {
                $table->dropUnique(self::SOFT_DELETE_UNIQUE_INDEX);
            });
        }

        $indexes = array_column(Schema::getIndexes('vehicles'), 'name');

        if (! in_array(self::LEGACY_UNIQUE_INDEX, $indexes, true)) {
            Schema::table('vehicles', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'registration_number'], self::LEGACY_UNIQUE_INDEX);
            });
        }
    }
};
