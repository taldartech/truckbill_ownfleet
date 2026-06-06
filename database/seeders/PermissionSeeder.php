<?php

namespace Database\Seeders;

use App\Permission;
use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, group: string}>
     */
    private const PERMISSIONS = [
        'dashboard.view' => ['name' => 'View dashboard', 'group' => 'dashboard'],
        'drivers.view' => ['name' => 'View drivers', 'group' => 'drivers'],
        'drivers.manage' => ['name' => 'Manage drivers', 'group' => 'drivers'],
        'attendances.view' => ['name' => 'View attendance', 'group' => 'attendance'],
        'attendances.manage' => ['name' => 'Manage attendance', 'group' => 'attendance'],
        'wages.manage' => ['name' => 'Manage driver wages', 'group' => 'attendance'],
        'driver_assignments.view' => ['name' => 'View vehicle driver assignments', 'group' => 'drivers'],
        'driver_assignments.manage' => ['name' => 'Manage vehicle driver assignments', 'group' => 'drivers'],
        'vehicles.view' => ['name' => 'View trucks/vehicles', 'group' => 'vehicles'],
        'vehicles.manage' => ['name' => 'Manage trucks/vehicles', 'group' => 'vehicles'],
        'vehicles.delete' => ['name' => 'Delete trucks/vehicles', 'group' => 'vehicles'],
        'vehicles.toggle_status' => ['name' => 'Activate or deactivate trucks', 'group' => 'vehicles'],
        'vehicle_documents.view' => ['name' => 'View vehicle documents', 'group' => 'vehicles'],
        'vehicle_documents.manage' => ['name' => 'Manage vehicle documents', 'group' => 'vehicles'],
        'companies.view' => ['name' => 'View companies', 'group' => 'companies'],
        'companies.create' => ['name' => 'Create company', 'group' => 'companies'],
        'companies.update' => ['name' => 'Update company', 'group' => 'companies'],
        'companies.enter' => ['name' => 'Enter company', 'group' => 'companies'],
        'companies.block' => ['name' => 'Block company', 'group' => 'companies'],
        'reports.view' => ['name' => 'View attendance reports', 'group' => 'reports'],
        'reports.export' => ['name' => 'Export attendance reports', 'group' => 'reports'],
        'trips.view' => ['name' => 'View trips', 'group' => 'trips'],
        'trips.manage' => ['name' => 'Manage trips', 'group' => 'trips'],
        'trip_reports.view' => ['name' => 'View trip reports', 'group' => 'trips'],
        'trip_reports.export' => ['name' => 'Export trip reports', 'group' => 'trips'],
        'stations.view' => ['name' => 'View stations', 'group' => 'trips'],
        'stations.manage' => ['name' => 'Manage stations', 'group' => 'trips'],
        'locations.view' => ['name' => 'View states and cities', 'group' => 'locations'],
        'locations.manage' => ['name' => 'Manage states and cities', 'group' => 'locations'],
        'tyres.view' => ['name' => 'View tyres', 'group' => 'tyres'],
        'tyres.manage' => ['name' => 'Manage tyres', 'group' => 'tyres'],
        'tyre_layouts.view' => ['name' => 'View tyre layouts', 'group' => 'tyres'],
        'tyre_layouts.manage' => ['name' => 'Manage tyre layouts', 'group' => 'tyres'],
        'tyre_reports.view' => ['name' => 'View tyre reports', 'group' => 'tyres'],
        'challans.view' => ['name' => 'View challans', 'group' => 'compliance'],
        'challans.manage' => ['name' => 'Create & edit challans', 'group' => 'compliance'],
        'challans.delete' => ['name' => 'Delete challans', 'group' => 'compliance'],
        'emis.view' => ['name' => 'View EMIs', 'group' => 'compliance'],
        'emis.manage' => ['name' => 'Create & edit EMIs', 'group' => 'compliance'],
        'emis.delete' => ['name' => 'Delete EMIs', 'group' => 'compliance'],
        'roles.view' => ['name' => 'View roles', 'group' => 'access'],
        'roles.manage' => ['name' => 'Manage role permissions', 'group' => 'access'],
        'permissions.view' => ['name' => 'View permissions', 'group' => 'access'],
        'permissions.manage' => ['name' => 'Manage permissions', 'group' => 'access'],
        'users.view' => ['name' => 'View company users', 'group' => 'access'],
        'users.manage' => ['name' => 'Manage company users', 'group' => 'access'],
        'super_admins.view' => ['name' => 'View super admins', 'group' => 'access'],
        'super_admins.manage' => ['name' => 'Manage super admins', 'group' => 'access'],
    ];

    /**
     * @var array<int, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        UserRole::MASTER_ADMIN => [
            'companies.view',
            'companies.update',
            'companies.block',
            'drivers.view',
            'roles.view',
            'roles.manage',
            'permissions.view',
            'permissions.manage',
            'super_admins.view',
            'super_admins.manage',
        ],
        UserRole::SUPER_ADMIN => [
            'dashboard.view',
            'drivers.view',
            'drivers.manage',
            'driver_assignments.view',
            'driver_assignments.manage',
            'attendances.view',
            'attendances.manage',
            'wages.manage',
            'vehicles.view',
            'vehicles.manage',
            'vehicles.delete',
            'vehicles.toggle_status',
            'vehicle_documents.view',
            'vehicle_documents.manage',
            'reports.view',
            'reports.export',
            'companies.view',
            'companies.create',
            'companies.update',
            'companies.enter',
            'trips.view',
            'trips.manage',
            'trip_reports.view',
            'trip_reports.export',
            'stations.view',
            'stations.manage',
            'locations.view',
            'locations.manage',
            'tyres.view',
            'tyres.manage',
            'tyre_layouts.view',
            'tyre_layouts.manage',
            'tyre_reports.view',
            'challans.view',
            'challans.manage',
            'challans.delete',
            'emis.view',
            'emis.manage',
            'emis.delete',
            'users.view',
            'users.manage',
        ],
        UserRole::ADMIN => [
            'dashboard.view',
            'drivers.view',
            'drivers.manage',
            'driver_assignments.view',
            'driver_assignments.manage',
            'attendances.view',
            'attendances.manage',
            'wages.manage',
            'vehicles.view',
            'vehicles.manage',
            'vehicles.toggle_status',
            'vehicle_documents.view',
            'vehicle_documents.manage',
            'reports.view',
            'reports.export',
            'trips.view',
            'trips.manage',
            'trip_reports.view',
            'trip_reports.export',
            'stations.view',
            'stations.manage',
            'locations.view',
            'locations.manage',
            'tyres.view',
            'tyres.manage',
            'tyre_layouts.view',
            'tyre_layouts.manage',
            'tyre_reports.view',
            'challans.view',
            'challans.manage',
            'emis.view',
            'emis.manage',
        ],
        UserRole::MODERATOR => [
            'dashboard.view',
            'drivers.view',
            'driver_assignments.view',
            'attendances.view',
            'vehicles.view',
            'vehicle_documents.view',
            'reports.view',
            'trips.view',
            'trip_reports.view',
            'stations.view',
            'locations.view',
            'tyres.view',
            'tyre_layouts.view',
            'tyre_reports.view',
            'challans.view',
            'emis.view',
        ],
        UserRole::ACCOUNTANT => [
            'dashboard.view',
            'drivers.view',
            'attendances.view',
            'wages.manage',
            'vehicles.view',
            'vehicle_documents.view',
            'reports.view',
            'reports.export',
            'trips.view',
            'trip_reports.view',
            'trip_reports.export',
            'stations.view',
            'locations.view',
            'tyres.view',
            'tyre_reports.view',
            'challans.view',
            'challans.manage',
            'emis.view',
            'emis.manage',
        ],
    ];

    public function run(): void
    {
        $now = now();
        $permissionIds = [];

        foreach (self::PERMISSIONS as $slug => $meta) {
            $permission = Permission::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $meta['name'],
                    'group' => $meta['group'],
                ],
            );

            $permissionIds[$slug] = $permission->id;
        }

        DB::table('permission_user_role')->delete();

        foreach (self::ROLE_PERMISSIONS as $roleId => $slugs) {
            foreach ($slugs as $slug) {
                DB::table('permission_user_role')->insert([
                    'permission_id' => $permissionIds[$slug],
                    'user_role_id' => $roleId,
                ]);
            }
        }
    }
}
