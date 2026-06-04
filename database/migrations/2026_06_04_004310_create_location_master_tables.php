<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('countries')) {
            Schema::create('countries', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100);
                $table->string('code', 3)->nullable();
                $table->timestamps();

                $table->unique('name');
            });
        }

        if (! Schema::hasTable('states')) {
            Schema::create('states', function (Blueprint $table) {
                $table->id();
                $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('code', 10)->nullable();
                $table->timestamps();

                $table->unique(['country_id', 'name']);
            });
        }

        if (! Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('state_id')->constrained('states')->cascadeOnDelete();
                $table->string('name', 120);
                $table->timestamps();

                $table->unique(['state_id', 'name']);
            });
        }

        if (Schema::hasTable('stations')) {
            Schema::table('stations', function (Blueprint $table) {
                $indexes = Schema::getIndexes('stations');
                $indexNames = array_column($indexes, 'name');

                if (! in_array('stations_tenant_id_state_id_city_id_name_unique', $indexNames, true)) {
                    $table->unique(['tenant_id', 'state_id', 'city_id', 'name'], 'stations_tenant_id_state_id_city_id_name_unique');
                }
            });

            Schema::table('stations', function (Blueprint $table) {
                $indexes = Schema::getIndexes('stations');
                $indexNames = array_column($indexes, 'name');

                if (in_array('stations_tenant_id_name_unique', $indexNames, true)) {
                    $table->dropUnique('stations_tenant_id_name_unique');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stations')) {
            Schema::table('stations', function (Blueprint $table) {
                $indexes = Schema::getIndexes('stations');
                $indexNames = array_column($indexes, 'name');

                if (! in_array('stations_tenant_id_name_unique', $indexNames, true)) {
                    $table->unique(['tenant_id', 'name']);
                }
            });

            Schema::table('stations', function (Blueprint $table) {
                $indexes = Schema::getIndexes('stations');
                $indexNames = array_column($indexes, 'name');

                if (in_array('stations_tenant_id_state_id_city_id_name_unique', $indexNames, true)) {
                    $table->dropUnique('stations_tenant_id_state_id_city_id_name_unique');
                }
            });
        }

        Schema::dropIfExists('cities');
        Schema::dropIfExists('states');
        Schema::dropIfExists('countries');
    }
};
