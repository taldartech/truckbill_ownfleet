<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $column = DB::selectOne("SHOW COLUMNS FROM personal_access_tokens WHERE Field = 'tokenable_id'");

        if ($column === null || str_contains(strtolower((string) $column->Type), 'char')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex('personal_access_tokens_tokenable_type_tokenable_id_index');
        });

        DB::statement('ALTER TABLE personal_access_tokens MODIFY tokenable_id CHAR(36) NOT NULL');

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $column = DB::selectOne("SHOW COLUMNS FROM personal_access_tokens WHERE Field = 'tokenable_id'");

        if ($column === null || str_contains(strtolower((string) $column->Type), 'bigint')) {
            return;
        }

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropIndex('personal_access_tokens_tokenable_type_tokenable_id_index');
        });

        DB::statement('ALTER TABLE personal_access_tokens MODIFY tokenable_id BIGINT UNSIGNED NOT NULL');

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }
};
