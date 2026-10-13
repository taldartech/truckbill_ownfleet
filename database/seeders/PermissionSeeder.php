<?php

namespace Database\Seeders;

use App\Permission;
use App\Support\PermissionCatalog;
use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const COMPANY_ADMIN_PERMISSIONS = [
        'dashboard.view',
        'drivers.view',
        'drivers.manage',
        'drivers.archive',
        'driver_advances.view',
        'driver_advances.manage',
        'driver_assignments.view',
        'driver_assignments.manage',
        'attendances.view',
        'attendances.manage',
        'attendances.bulk_manage',
        'wages.view',
        'wages.manage',
        'vehicles.view',
        'vehicles.manage',
        'vehicles.delete',
        'vehicles.toggle_status',
        'vehicle_documents.view',
        'vehicle_documents.manage',
        'reports.view',
        'reports.export',
        'alerts.view',
        'trips.view',
        'trips.manage',
        'trips.change_status',
        'trips.expenses',
        'trips.archive',
        'trips.view_archived',
        'trips.restore',
        'trips.delete',
        'trip_reports.view',
        'trip_reports.export',
        'fleet_monthly_report.view',
        'fleet_monthly_report.export',
        'stations.view',
        'stations.manage',
        'brokers.view',
        'brokers.manage',
        'customers.view',
        'customers.manage',
        'goods_types.view',
        'goods_types.manage',
        'trip_expense_types.view',
        'trip_expense_types.delete',
        'trip_expense_types.create',
        'trip_expense_types.edit',
        'invoices.view',
        'invoices.create',
        'invoices.edit',
        'invoices.manage',
        'invoices.delete',
        'invoices.view_archived',
        'invoices.export',
        'locations.view',
        'locations.manage',
        'tyres.view',
        'tyres.manage',
        'tyre_layouts.view',
        'tyre_layouts.create',
        'tyre_layouts.edit',
        'tyre_layouts.manage',
        'tyre_reports.view',
        'tyre_reports.export',
        'batteries.view',
        'batteries.manage',
        'batteries.create',
        'batteries.edit',
        'batteries.delete',
        'maintenance.view',
        'maintenance.create',
        'maintenance.edit',
        'maintenance.delete',
        'maintenance_reports.view',
        'maintenance_reports.export',
        'emis.view',
        'emis.manage',
        'monthly_expenses.view',
        'monthly_expenses.manage',
    ];

    /**
     * @var list<string>
     */
    private const COMPANY_MODERATOR_PERMISSIONS = [
        'dashboard.view',
        'dashboard.widgets.trips',
        'dashboard.widgets.fleet',
        'dashboard.widgets.attendance',
        'drivers.view',
        'drivers.create',
        'driver_assignments.view',
        'driver_assignments.create',
        'attendances.view',
        'attendances.create',
        'attendance.bulk_mark',
        'trips.view',
        'trips.create',
        'trips.change_status',
        'trips.expenses',
        'vehicles.view',
        'vehicles.create',
        'vehicle_documents.view',
        'vehicle_documents.create',
        'vehicle_documents.edit',
        'maintenance.view',
        'maintenance.create',
        'trip_reports.view',
        'attendance_reports.view',
        'maintenance_reports.view',
        'tyre_reports.view',
        'alerts.view',
        'notifications.view',
        'stations.view',
        'stations.create',
        'brokers.view',
        'brokers.create',
        'customers.view',
        'customers.create',
        'challans.view',
        'challans.create',
        'monthly_expenses.view',
        'monthly_expenses.create',
        'invoices.export',
        'fleet_monthly_report.view',
        'invoices.view',
        'invoices.create',
        'goods_types.view',
        'goods_types.create',
        'trip_expense_types.view',
        'trip_expense_types.create',
        'locations.view',
        'tyres.view',
        'tyre_layouts.view',
        'tyre_layouts.create',
        'batteries.view',
        'batteries.create',
    ];

    /**
     * @var list<string>
     */
    private const MASTER_ADMIN_PERMISSIONS = [
        'companies.view',
        'companies.update',
        'companies.block',
        'drivers.view',
        'roles.view',
        'roles.manage',
        'permissions.view',
        'alerts.view',
        'permissions.manage',
        'super_admins.view',
        'super_admins.manage',
        'locations.view',
        'locations.create',
        'locations.edit',
        'locations.manage',
    ];

    /**
     * @var list<string>
     */
    private const ACCOUNTANT_PERMISSIONS = [
        'dashboard.view',
        'dashboard.widgets.financial',
        'dashboard.widgets.payroll',
        'drivers.view',
        'driver_assignments.view',
        'attendances.view',
        'wages.view',
        'vehicles.view',
        'vehicle_documents.view',
        'attendance_reports.view',
        'attendance_reports.export',
        'trips.view',
        'trip_reports.view',
        'fleet_monthly_report.view',
        'fleet_monthly_report.export',
        'stations.view',
        'brokers.view',
        'customers.view',
        'invoices.view',
        'invoices.export',
        'goods_types.view',
        'trip_expense_types.view',
        'locations.view',
        'tyres.view',
        'tyre_layouts.view',
        'tyre_reports.view',
        'batteries.view',
        'maintenance.view',
        'maintenance_reports.view',
        'alerts.view',
        'emis.view',
        'monthly_expenses.view',
        'notifications.view',
    ];

    /**
     * @var array<int, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        UserRole::MASTER_ADMIN => self::MASTER_ADMIN_PERMISSIONS,
        UserRole::SUPER_ADMIN => [],
        UserRole::ADMIN => self::COMPANY_ADMIN_PERMISSIONS,
        UserRole::MODERATOR => self::COMPANY_MODERATOR_PERMISSIONS,
        UserRole::ACCOUNTANT => self::ACCOUNTANT_PERMISSIONS,
    ];

    public function run(): void
    {
        PermissionCatalog::validate();

        $permissionIds = [];

        foreach (PermissionCatalog::definitions() as $slug => $meta) {
            $permission = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $meta['name'],
                    'group' => $meta['group'],
                    'description' => $meta['description'],
                    'display_order' => $meta['display_order'],
                ],
            );

            $permissionIds[$slug] = $permission->id;
        }

        DB::table('permission_user_role')->delete();

        foreach (self::ROLE_PERMISSIONS as $roleId => $slugs) {
            $resolvedSlugs = $roleId === UserRole::SUPER_ADMIN
                ? PermissionCatalog::superAdminSlugs()
                : $slugs;

            foreach ($resolvedSlugs as $slug) {
                DB::table('permission_user_role')->insert([
                    'permission_id' => $permissionIds[$slug],
                    'user_role_id' => $roleId,
                ]);
            }
        }

        Artisan::call('permissions:clear-cache');
    }
}
