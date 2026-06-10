<?php

use App\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<int, int>
     */
    private const LEGACY_ROLE_FALLBACK = [
        5 => UserRole::ADMIN,
        6 => UserRole::MODERATOR,
        7 => UserRole::MODERATOR,
    ];

    public function up(): void
    {
        foreach (self::LEGACY_ROLE_FALLBACK as $legacyRoleId => $replacementRoleId) {
            DB::table('users')
                ->where('user_role_id', $legacyRoleId)
                ->update(['user_role_id' => $replacementRoleId]);
        }

        DB::table('permission_user_role')
            ->whereIn('user_role_id', array_keys(self::LEGACY_ROLE_FALLBACK))
            ->delete();

        DB::table('user_roles')
            ->whereIn('id', array_keys(self::LEGACY_ROLE_FALLBACK))
            ->delete();
    }

    public function down(): void
    {
        $now = now();

        foreach ([
            5 => 'accountant',
            6 => 'transporter',
            7 => 'customer',
        ] as $id => $name) {
            DB::table('user_roles')->insert([
                'id' => $id,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
