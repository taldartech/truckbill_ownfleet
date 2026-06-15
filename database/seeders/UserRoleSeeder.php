<?php

namespace Database\Seeders;

use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach (UserRole::NAMES as $id => $name) {
            DB::table('user_roles')->updateOrInsert(
                ['id' => $id],
                [
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        UserRole::query()
            ->whereNotIn('id', UserRole::systemRoleIds())
            ->delete();
    }
}
