<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stations')) {
            Schema::create('stations', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->unsignedBigInteger('country_id')->nullable();
                $table->unsignedBigInteger('state_id')->nullable();
                $table->unsignedBigInteger('city_id')->nullable();
                $table->unsignedBigInteger('agency_id')->nullable();
                $table->string('name', 150);
                $table->softDeletes();
                $table->timestamps();

                $table->unique(['tenant_id', 'name']);
            });
        } else {
            Schema::table('stations', function (Blueprint $table) {
                if (! Schema::hasColumn('stations', 'tenant_id')) {
                    $table->foreignUuid('tenant_id')->nullable()->after('id')->constrained('tenants')->nullOnDelete();
                }
            });
        }

        Schema::table('trips', function (Blueprint $table) {
            if (! Schema::hasColumn('trips', 'start_station_id')) {
                $table->unsignedBigInteger('start_station_id')->nullable()->after('broker_name');
            }

            if (! Schema::hasColumn('trips', 'half_loaded_distance_km')) {
                $table->decimal('half_loaded_distance_km', 12, 2)->default(0)->after('loaded_distance_km');
            }
        });

        Schema::table('trip_route_legs', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_route_legs', 'from_station_id')) {
                $table->unsignedBigInteger('from_station_id')->nullable()->after('trip_id');
            }

            if (! Schema::hasColumn('trip_route_legs', 'to_station_id')) {
                $table->unsignedBigInteger('to_station_id')->nullable()->after('from_station_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trip_route_legs', function (Blueprint $table) {
            $table->dropColumn(['from_station_id', 'to_station_id']);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['start_station_id', 'half_loaded_distance_km']);
        });

        Schema::dropIfExists('stations');
    }
};
