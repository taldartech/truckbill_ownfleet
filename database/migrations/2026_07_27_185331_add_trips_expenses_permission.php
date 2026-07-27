<?php

use App\Permission;
use App\Support\PermissionCache;
use App\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SLUG = 'trips.expenses';

    /**
     * @var list<int>
     */
    private const ROLE_IDS = [
        UserRole::SUPER_ADMIN,
        UserRole::ADMIN,
        UserRole::MODERATOR,
    ];

    public function up(): void
    {
        $permission = Permission::query()->updateOrCreate(
            ['slug' => self::SLUG],
            [
                'name' => 'Manage trip expenses',
                'group' => 'trips',
                'description' => 'Add and update expenses on trips.',
                'display_order' => 203,
            ],
        );

        foreach (self::ROLE_IDS as $roleId) {
            $exists = DB::table('permission_user_role')
                ->where('permission_id', $permission->id)
                ->where('user_role_id', $roleId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('permission_user_role')->insert([
                'permission_id' => $permission->id,
                'user_role_id' => $roleId,
            ]);
        }

        PermissionCache::flush();
    }

    public function down(): void
    {
        $permission = Permission::query()->where('slug', self::SLUG)->first();

        if ($permission === null) {
            return;
        }

        DB::table('permission_user_role')
            ->where('permission_id', $permission->id)
            ->delete();

        $permission->delete();

        PermissionCache::flush();
    }
};
