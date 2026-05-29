<?php

namespace Database\Seeders;

use App\UserRole;
use Illuminate\Database\Seeder;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UserRole::NAMES as $id => $name) {
            UserRole::query()->updateOrCreate(
                ['id' => $id],
                ['name' => $name],
            );
        }
    }
}
