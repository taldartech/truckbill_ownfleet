<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserRoleSeeder::class,
            PermissionSeeder::class,
            IndiaLocationSeeder::class,
            MasterAdminSeeder::class,
            TripExpenseTypeSeeder::class,
        ]);
    }
}
