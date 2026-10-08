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
            ['code' => 'TOLL', 'name' => 'Toll'],
            ['code' => 'LOADING_CHARGES', 'name' => 'Loading Charges'],
            ['code' => 'UNLOADING_CHARGES', 'name' => 'Unloading Charges'],
            ['code' => 'DRIVER_EXPENSE', 'name' => 'Driver Expense'],
            ['code' => 'FOOD_EXPENSE', 'name' => 'Food Expense'],
            ['code' => 'REPAIR', 'name' => 'Repair'],
            ['code' => 'PARKING', 'name' => 'Parking'],
            ['code' => 'RTO_EXPENSE', 'name' => 'RTO'],
            ['code' => 'PERMIT_BORDER_TAX', 'name' => 'Permit / Border Tax'],
            ['code' => 'OTHER', 'name' => 'Other'],
        ];

        foreach ($types as $type) {
            \App\TripExpenseTypeMaster::query()->firstOrCreate(
                ['code' => $type['code']],
                ['name' => $type['name'], 'is_active' => true, 'tenant_id' => null]
            );
        }
    }
}
