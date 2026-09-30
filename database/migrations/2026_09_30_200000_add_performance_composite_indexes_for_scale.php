<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Trips table composite indexes
        Schema::table('trips', function (Blueprint $table): void {
            if (! $this->indexExists('trips', 'trips_tenant_status_start_at_idx')) {
                $table->index(['tenant_id', 'status', 'start_at'], 'trips_tenant_status_start_at_idx');
            }
            if (! $this->indexExists('trips', 'trips_tenant_start_at_idx')) {
                $table->index(['tenant_id', 'start_at'], 'trips_tenant_start_at_idx');
            }
            if (! $this->indexExists('trips', 'trips_tenant_branch_status_idx')) {
                $table->index(['tenant_id', 'branch_id', 'status'], 'trips_tenant_branch_status_idx');
            }
            if (! $this->indexExists('trips', 'trips_tenant_deleted_created_idx')) {
                $table->index(['tenant_id', 'deleted_at', 'created_at'], 'trips_tenant_deleted_created_idx');
            }
        });

        // 2. Trip expenses table composite indexes
        Schema::table('trip_expenses', function (Blueprint $table): void {
            if (! $this->indexExists('trip_expenses', 'trip_expenses_tenant_trip_type_idx')) {
                $table->index(['tenant_id', 'trip_id', 'expense_type'], 'trip_expenses_tenant_trip_type_idx');
            }
            if (! $this->indexExists('trip_expenses', 'trip_expenses_tenant_type_date_idx')) {
                $table->index(['tenant_id', 'expense_type', 'expense_date'], 'trip_expenses_tenant_type_date_idx');
            }
        });

        // 3. Invoices table composite indexes
        Schema::table('invoices', function (Blueprint $table): void {
            if (! $this->indexExists('invoices', 'invoices_tenant_status_date_idx')) {
                $table->index(['tenant_id', 'payment_status', 'invoice_date'], 'invoices_tenant_status_date_idx');
            }
            if (! $this->indexExists('invoices', 'invoices_tenant_customer_status_idx')) {
                $table->index(['tenant_id', 'customer_id', 'payment_status'], 'invoices_tenant_customer_status_idx');
            }
        });

        // 4. Vehicles table composite indexes
        Schema::table('vehicles', function (Blueprint $table): void {
            if (! $this->indexExists('vehicles', 'vehicles_tenant_active_idx')) {
                $table->index(['tenant_id', 'is_active'], 'vehicles_tenant_active_idx');
            }
        });

        // 5. Drivers table composite indexes
        Schema::table('drivers', function (Blueprint $table): void {
            if (! $this->indexExists('drivers', 'drivers_tenant_active_license_idx')) {
                $table->index(['tenant_id', 'is_active', 'license_expiry_date'], 'drivers_tenant_active_license_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            if ($this->indexExists('trips', 'trips_tenant_status_start_at_idx')) {
                $table->dropIndex('trips_tenant_status_start_at_idx');
            }
            if ($this->indexExists('trips', 'trips_tenant_start_at_idx')) {
                $table->dropIndex('trips_tenant_start_at_idx');
            }
            if ($this->indexExists('trips', 'trips_tenant_branch_status_idx')) {
                $table->dropIndex('trips_tenant_branch_status_idx');
            }
            if ($this->indexExists('trips', 'trips_tenant_deleted_created_idx')) {
                $table->dropIndex('trips_tenant_deleted_created_idx');
            }
        });

        Schema::table('trip_expenses', function (Blueprint $table): void {
            if ($this->indexExists('trip_expenses', 'trip_expenses_tenant_trip_type_idx')) {
                $table->dropIndex('trip_expenses_tenant_trip_type_idx');
            }
            if ($this->indexExists('trip_expenses', 'trip_expenses_tenant_type_date_idx')) {
                $table->dropIndex('trip_expenses_tenant_type_date_idx');
            }
        });

        Schema::table('invoices', function (Blueprint $table): void {
            if ($this->indexExists('invoices', 'invoices_tenant_status_date_idx')) {
                $table->dropIndex('invoices_tenant_status_date_idx');
            }
            if ($this->indexExists('invoices', 'invoices_tenant_customer_status_idx')) {
                $table->dropIndex('invoices_tenant_customer_status_idx');
            }
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            if ($this->indexExists('vehicles', 'vehicles_tenant_active_idx')) {
                $table->dropIndex('vehicles_tenant_active_idx');
            }
        });

        Schema::table('drivers', function (Blueprint $table): void {
            if ($this->indexExists('drivers', 'drivers_tenant_active_license_idx')) {
                $table->dropIndex('drivers_tenant_active_license_idx');
            }
        });
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
