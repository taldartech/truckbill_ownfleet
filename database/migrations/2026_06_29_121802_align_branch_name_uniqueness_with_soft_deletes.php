<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_UNIQUE_INDEX = 'branches_company_id_branch_name_unique';

    private const SOFT_DELETE_UNIQUE_INDEX = 'branches_company_id_branch_name_deleted_at_unique';

    private const COMPANY_ID_INDEX = 'branches_company_id_index';

    public function up(): void
    {
        $indexes = array_column(Schema::getIndexes('branches'), 'name');

        if (in_array(self::LEGACY_UNIQUE_INDEX, $indexes, true)) {
            if (! $this->hasStandaloneCompanyIdIndex()) {
                Schema::table('branches', function (Blueprint $table): void {
                    $table->index('company_id', self::COMPANY_ID_INDEX);
                });
            }

            Schema::table('branches', function (Blueprint $table): void {
                $table->dropUnique(['company_id', 'branch_name']);
            });
        }

        $indexes = array_column(Schema::getIndexes('branches'), 'name');

        if (! in_array(self::SOFT_DELETE_UNIQUE_INDEX, $indexes, true)) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->unique(['company_id', 'branch_name', 'deleted_at'], self::SOFT_DELETE_UNIQUE_INDEX);
            });
        }
    }

    public function down(): void
    {
        $indexes = array_column(Schema::getIndexes('branches'), 'name');

        if (in_array(self::SOFT_DELETE_UNIQUE_INDEX, $indexes, true)) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->dropUnique(self::SOFT_DELETE_UNIQUE_INDEX);
            });
        }

        $indexes = array_column(Schema::getIndexes('branches'), 'name');

        if (! in_array(self::LEGACY_UNIQUE_INDEX, $indexes, true)) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->unique(['company_id', 'branch_name']);
            });
        }

        $indexes = array_column(Schema::getIndexes('branches'), 'name');

        if (in_array(self::COMPANY_ID_INDEX, $indexes, true)) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->dropIndex(self::COMPANY_ID_INDEX);
            });
        }
    }

    private function hasStandaloneCompanyIdIndex(): bool
    {
        foreach (Schema::getIndexes('branches') as $index) {
            if (($index['columns'] ?? []) === ['company_id']) {
                return true;
            }
        }

        return false;
    }
};
