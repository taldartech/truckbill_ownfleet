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
        'vehicle_documents.view' => ['name' => 'View vehicle documents', 'group' => 'vehicles'],
        'vehicle_documents.manage' => ['name' => 'Manage vehicle documents', 'group' => 'vehicles'],
        'companies.view' => ['name' => 'View companies', 'group' => 'companies'],
        'companies.create' => ['name' => 'Create company', 'group' => 'companies'],
        'companies.update' => ['name' => 'Update company', 'group' => 'companies'],
        'companies.enter' => ['name' => 'Enter company', 'group' => 'companies'],
        'companies.block' => ['name' => 'Block company', 'group' => 'companies'],
        'reports.view' => ['name' => 'View attendance reports', 'group' => 'reports'],
        'reports.export' => ['name' => 'Export attendance reports', 'group' => 'reports'],
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
            'vehicle_documents.view',
            'vehicle_documents.manage',
            'reports.view',
            'reports.export',
            'companies.view',
            'companies.create',
            'companies.update',
            'companies.enter',
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
            'vehicle_documents.view',
            'vehicle_documents.manage',
            'reports.view',
            'reports.export',
        ],
        UserRole::MODERATOR => [
            'dashboard.view',
            'drivers.view',
            'driver_assignments.view',
            'attendances.view',
            'vehicles.view',
            'vehicle_documents.view',
            'reports.view',
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
