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
        DB::table('trip_route_legs')
            ->join('stations', 'stations.id', '=', 'trip_route_legs.from_station_id')
            ->update(['trip_route_legs.from_city' => DB::raw('stations.name')]);

        DB::table('trip_route_legs')
            ->join('stations', 'stations.id', '=', 'trip_route_legs.to_station_id')
            ->update(['trip_route_legs.to_city' => DB::raw('stations.name')]);

        $addressSql = "NULLIF(TRIM(CONCAT_WS(', ', NULLIF(TRIM(customers.address), ''), NULLIF(TRIM(cities.name), ''), NULLIF(TRIM(states.name), ''))), '')";

        DB::table('trip_route_legs')
            ->join('customers', function ($join): void {
                $join->on('customers.tenant_id', '=', 'trip_route_legs.tenant_id')
                    ->on('customers.name', '=', 'trip_route_legs.consignor')
                    ->whereNull('customers.deleted_at');
            })
            ->leftJoin('cities', 'cities.id', '=', 'customers.city_id')
            ->leftJoin('states', 'states.id', '=', 'customers.state_id')
            ->whereNotNull('trip_route_legs.consignor')
            ->where('trip_route_legs.consignor', '!=', '')
            ->update(['trip_route_legs.from_address' => DB::raw($addressSql)]);

        DB::table('trip_route_legs')
            ->join('customers', function ($join): void {
                $join->on('customers.tenant_id', '=', 'trip_route_legs.tenant_id')
                    ->on('customers.name', '=', 'trip_route_legs.consignee')
                    ->whereNull('customers.deleted_at');
            })
            ->leftJoin('cities', 'cities.id', '=', 'customers.city_id')
            ->leftJoin('states', 'states.id', '=', 'customers.state_id')
            ->whereNotNull('trip_route_legs.consignee')
            ->where('trip_route_legs.consignee', '!=', '')
            ->update(['trip_route_legs.to_address' => DB::raw($addressSql)]);

        DB::table('trip_route_legs')
            ->join('customers', 'customers.id', '=', 'trip_route_legs.customer_id')
            ->leftJoin('cities', 'cities.id', '=', 'customers.city_id')
            ->leftJoin('states', 'states.id', '=', 'customers.state_id')
            ->whereNull('customers.deleted_at')
            ->where(function ($query): void {
                $query->whereNull('trip_route_legs.consignor')
                    ->orWhere('trip_route_legs.consignor', '');
            })
            ->where(function ($query): void {
                $query->whereNull('trip_route_legs.from_address')
                    ->orWhere('trip_route_legs.from_address', '');
            })
            ->update(['trip_route_legs.from_address' => DB::raw($addressSql)]);

        if (Schema::hasColumn('trip_route_legs', 'from_location')) {
            DB::table('trip_route_legs')
                ->where(function ($query): void {
                    $query->whereNull('from_city')->orWhere('from_city', '');
                })
                ->whereNotNull('from_location')
                ->where('from_location', '!=', '')
                ->update(['from_city' => DB::raw('from_location')]);

            DB::table('trip_route_legs')
                ->where(function ($query): void {
                    $query->whereNull('to_city')->orWhere('to_city', '');
                })
                ->whereNotNull('to_location')
                ->where('to_location', '!=', '')
                ->update(['to_city' => DB::raw('to_location')]);

            DB::table('trip_route_legs')
                ->where(function ($query): void {
                    $query->whereNull('from_address')->orWhere('from_address', '');
                })
                ->whereNotNull('from_location')
                ->where('from_location', '!=', '')
                ->update(['from_address' => DB::raw('from_location')]);

            DB::table('trip_route_legs')
                ->where(function ($query): void {
                    $query->whereNull('to_address')->orWhere('to_address', '');
                })
                ->whereNotNull('to_location')
                ->where('to_location', '!=', '')
                ->update(['to_address' => DB::raw('to_location')]);

            Schema::table('trip_route_legs', function (Blueprint $table) {
                $table->dropColumn([
                    'from_location',
                    'to_location',
                ]);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_route_legs', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_route_legs', 'from_location')) {
                $table->string('from_location')->nullable()->after('to_station_id');
                $table->string('to_location')->nullable()->after('from_location');
            }
        });
    }
};
