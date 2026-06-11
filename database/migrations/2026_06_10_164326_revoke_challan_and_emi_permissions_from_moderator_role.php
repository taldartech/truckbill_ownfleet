<?php

use App\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const REVOKED_SLUGS = [
        'challans.view',
        'challans.manage',
        'challans.delete',
        'emis.view',
        'emis.manage',
        'emis.delete',
    ];

    /**
     * @var list<string>
     */
    private const RESTORED_VIEW_SLUGS = [
        'challans.view',
        'emis.view',
    ];

    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', self::REVOKED_SLUGS)
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('permission_user_role')
            ->where('user_role_id', UserRole::MODERATOR)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', self::RESTORED_VIEW_SLUGS)
            ->pluck('id', 'slug');

        foreach (self::RESTORED_VIEW_SLUGS as $slug) {
            $permissionId = $permissionIds[$slug] ?? null;

            if ($permissionId === null) {
                continue;
            }

            DB::table('permission_user_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'user_role_id' => UserRole::MODERATOR,
            ]);
        }
    }
};
