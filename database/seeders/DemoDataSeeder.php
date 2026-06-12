<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TestUserSeeder::class,
            DemoFleetDataSeeder::class,
            DemoTripDataSeeder::class,
        ]);
    }
}
