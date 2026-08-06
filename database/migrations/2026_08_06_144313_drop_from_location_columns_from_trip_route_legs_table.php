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
        $stationNames = DB::table('stations')->pluck('name', 'id');

        $customerAddresses = [];

        foreach (
            DB::table('customers')
                ->leftJoin('cities', 'cities.id', '=', 'customers.city_id')
                ->leftJoin('states', 'states.id', '=', 'customers.state_id')
                ->whereNull('customers.deleted_at')
                ->select([
                    'customers.id',
                    'customers.tenant_id',
                    'customers.name',
                    'customers.address',
                    'cities.name as city_name',
                    'states.name as state_name',
                ])
                ->get() as $customer
        ) {
            $parts = array_values(array_filter([
                trim((string) $customer->address),
                trim((string) $customer->city_name),
                trim((string) $customer->state_name),
            ], static fn (string $part): bool => $part !== ''));

            if ($parts === []) {
                continue;
            }

            $formatted = implode(', ', $parts);
            $customerAddresses['id:'.$customer->id] = $formatted;
            $customerAddresses['name:'.$customer->tenant_id.'|'.mb_strtolower(trim((string) $customer->name))] = $formatted;
        }

        $hasFromLocation = Schema::hasColumn('trip_route_legs', 'from_location');
        $hasToLocation = Schema::hasColumn('trip_route_legs', 'to_location');

        foreach (DB::table('trip_route_legs')->orderBy('id')->get() as $leg) {
            $fromCity = trim((string) ($stationNames[$leg->from_station_id] ?? $leg->from_city ?? ''));
            $toCity = trim((string) ($stationNames[$leg->to_station_id] ?? $leg->to_city ?? ''));

            if ($fromCity === '' && $hasFromLocation) {
                $fromCity = trim((string) ($leg->from_location ?? ''));
            }

            if ($toCity === '' && $hasToLocation) {
                $toCity = trim((string) ($leg->to_location ?? ''));
            }

            $consignor = trim((string) ($leg->consignor ?? ''));
            $consignee = trim((string) ($leg->consignee ?? ''));

            $fromAddress = trim((string) ($leg->from_address ?? ''));
            $toAddress = trim((string) ($leg->to_address ?? ''));

            if ($consignor !== '') {
                $fromAddress = $customerAddresses['name:'.$leg->tenant_id.'|'.mb_strtolower($consignor)] ?? $fromAddress;
            } elseif ($leg->customer_id) {
                $fromAddress = $customerAddresses['id:'.$leg->customer_id] ?? $fromAddress;
            }

            if ($consignee !== '') {
                $toAddress = $customerAddresses['name:'.$leg->tenant_id.'|'.mb_strtolower($consignee)] ?? $toAddress;
            }

            if ($fromAddress === '' && $hasFromLocation) {
                $fromAddress = trim((string) ($leg->from_location ?? ''));
            }

            if ($toAddress === '' && $hasToLocation) {
                $toAddress = trim((string) ($leg->to_location ?? ''));
            }

            DB::table('trip_route_legs')->where('id', $leg->id)->update([
                'from_city' => $fromCity !== '' ? $fromCity : null,
                'to_city' => $toCity !== '' ? $toCity : null,
                'from_address' => $fromAddress !== '' ? $fromAddress : null,
                'to_address' => $toAddress !== '' ? $toAddress : null,
            ]);
        }

        if ($hasFromLocation) {
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
