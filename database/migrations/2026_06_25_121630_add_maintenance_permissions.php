<?php

use App\Permission;
use App\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, array{name: string, group: string}>
     */
    private const PERMISSIONS = [
        'maintenance.view' => ['name' => 'View truck maintenance', 'group' => 'maintenance'],
        'maintenance.create' => ['name' => 'Create maintenance records', 'group' => 'maintenance'],
        'maintenance.edit' => ['name' => 'Edit maintenance records', 'group' => 'maintenance'],
        'maintenance.delete' => ['name' => 'Delete maintenance records', 'group' => 'maintenance'],
        'maintenance_reports.view' => ['name' => 'View maintenance reports', 'group' => 'maintenance'],
    ];

    /**
     * @var array<int, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        UserRole::SUPER_ADMIN => [
            'maintenance.view',
            'maintenance.create',
            'maintenance.edit',
            'maintenance.delete',
            'maintenance_reports.view',
        ],
        UserRole::ADMIN => [
            'maintenance.view',
            'maintenance.create',
            'maintenance.edit',
            'maintenance.delete',
            'maintenance_reports.view',
        ],
        UserRole::MODERATOR => [
            'maintenance.view',
            'maintenance.create',
            'maintenance.edit',
        ],
        UserRole::ACCOUNTANT => [
            'maintenance.view',
            'maintenance_reports.view',
        ],
    ];

    public function up(): void
    {
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

        foreach (self::ROLE_PERMISSIONS as $roleId => $slugs) {
            foreach ($slugs as $slug) {
                DB::table('permission_user_role')->insertOrIgnore([
                    'permission_id' => $permissionIds[$slug],
                    'user_role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', array_keys(self::PERMISSIONS))
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('permission_user_role')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        DB::table('permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }
};
