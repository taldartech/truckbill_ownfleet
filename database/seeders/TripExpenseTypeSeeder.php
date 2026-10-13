<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TripExpenseTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['code' => 'DIESEL', 'name' => 'Diesel'],
            ['code' => 'OTHER', 'name' => 'Other'],
        ];

        foreach ($types as $type) {
            \App\TripExpenseTypeMaster::query()->firstOrCreate(
                ['code' => $type['code']],
                ['name' => $type['name'], 'is_active' => true, 'super_admin_id' => null]
            );
        }
    }
}
