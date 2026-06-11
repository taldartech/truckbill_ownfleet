<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const CHALLAN_PERMISSION_SLUGS = [
        'challans.view',
        'challans.manage',
        'challans.delete',
    ];

    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', self::CHALLAN_PERMISSION_SLUGS)
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

    public function down(): void
    {
        $now = now();

        foreach ([
            'challans.view' => 'View challans',
            'challans.manage' => 'Create & edit challans',
            'challans.delete' => 'Delete challans',
        ] as $slug => $name) {
            DB::table('permissions')->insertOrIgnore([
                'slug' => $slug,
                'name' => $name,
                'group' => 'compliance',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
