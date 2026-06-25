<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const TABLES = [
        'vehicles',
        'drivers',
        'driver_assignments',
        'attendances',
        'trips',
        'trip_route_legs',
        'trip_expenses',
        'trip_freights',
        'trip_status_histories',
        'challans',
        'tyres',
        'tyre_installations',
        'tyre_rotations',
        'tyre_damages',
        'tyre_replacements',
        'tyre_expenses',
        'vehicle_documents',
        'document_types',
        'document_alert_settings',
        'brokers',
        'customers',
        'stations',
        'emis',
        'driver_advance_entries',
        'monthly_driver_wages',
        'attendance_month_locks',
        'app_notifications',
        'notification_logs',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'branch_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $column = $table->foreignId('branch_id')
                    ->nullable()
                    ->constrained('branches')
                    ->nullOnDelete();

                if (Schema::hasColumn($tableName, 'tenant_id')) {
                    $column->after('tenant_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'branch_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
