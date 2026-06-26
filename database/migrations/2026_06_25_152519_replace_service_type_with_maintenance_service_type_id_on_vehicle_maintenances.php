<?php

use App\Support\MaintenanceServiceTypeDefaults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tenantIds = DB::table('tenants')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            MaintenanceServiceTypeDefaults::seedForTenant((string) $tenantId);
        }

        Schema::table('vehicle_maintenances', function (Blueprint $table) {
            $table->foreignId('maintenance_service_type_id')
                ->nullable()
                ->after('odometer_km')
                ->constrained('maintenance_service_types')
                ->nullOnDelete();
        });

        $records = DB::table('vehicle_maintenances')
            ->select(['id', 'tenant_id', 'service_type'])
            ->whereNotNull('service_type')
            ->get();

        foreach ($records as $record) {
            $typeId = DB::table('maintenance_service_types')
                ->where('tenant_id', $record->tenant_id)
                ->where('code', $record->service_type)
                ->value('id');

            if ($typeId === null) {
                MaintenanceServiceTypeDefaults::seedForTenant((string) $record->tenant_id);

                $typeId = DB::table('maintenance_service_types')
                    ->where('tenant_id', $record->tenant_id)
                    ->where('code', $record->service_type)
                    ->value('id');
            }

            if ($typeId !== null) {
                DB::table('vehicle_maintenances')
                    ->where('id', $record->id)
                    ->update(['maintenance_service_type_id' => $typeId]);
            }
        }

        Schema::table('vehicle_maintenances', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_maintenances', function (Blueprint $table) {
            $table->string('service_type')->nullable()->after('odometer_km');
        });

        $records = DB::table('vehicle_maintenances')
            ->join(
                'maintenance_service_types',
                'maintenance_service_types.id',
                '=',
                'vehicle_maintenances.maintenance_service_type_id',
            )
            ->select([
                'vehicle_maintenances.id',
                'maintenance_service_types.code',
            ])
            ->get();

        foreach ($records as $record) {
            DB::table('vehicle_maintenances')
                ->where('id', $record->id)
                ->update(['service_type' => $record->code]);
        }

        Schema::table('vehicle_maintenances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maintenance_service_type_id');
        });
    }
};
