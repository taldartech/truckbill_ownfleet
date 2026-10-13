<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            IndiaLocationSeeder::class,
            TestUserSeeder::class,
            DemoFleetDataSeeder::class,
        ]);
    }
}
