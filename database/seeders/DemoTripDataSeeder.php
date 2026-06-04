<?php

namespace Database\Seeders;

use App\Driver;
use App\DriverAssignment;
use App\Enums\RouteType;
use App\Enums\TripExpenseType;
use App\Enums\TripStatus;
use App\Modules\TripManagement\Services\TripService;
use App\Support\TenantContext;
use App\Tenant;
use App\Trip;
use App\User;
use App\Vehicle;
use Illuminate\Database\Seeder;

class DemoTripDataSeeder extends Seeder
{
    public const DEFAULT_TENANT_SLUG = 'test-company';

    private const ADMIN_EMAIL = 'admin@test.truckbill.local';

    /**
     * @var list<array<string, mixed>>
     */
    private const TRIP_DEFINITIONS = [
        [
            'customer_name' => 'ABC Logistics',
            'broker_name' => 'Sharma Brokerage',
            'start_at' => '2026-01-08 07:30:00',
            'end_at' => '2026-01-11 19:00:00',
            'status' => TripStatus::Completed,
            'route_legs' => [
                [
                    'from_city' => 'Mumbai',
                    'from_address' => 'Andheri East, MIDC',
                    'to_city' => 'Pune',
                    'to_address' => 'Chakan Industrial Area',
                    'route_type' => RouteType::Loaded,
                    'sequence_no' => 1,
                    'distance_km' => 150,
                    'goods_name' => 'Steel coils',
                    'freight_amount' => 25000,
                    'advance_received' => 10000,
                ],
                [
                    'from_city' => 'Pune',
                    'to_city' => 'Mumbai',
                    'route_type' => RouteType::Empty,
                    'sequence_no' => 2,
                    'distance_km' => 150,
                ],
            ],
            'expenses' => [
                ['expense_type' => TripExpenseType::Diesel, 'amount' => 8500, 'route_leg_sequence' => 1],
                ['expense_type' => TripExpenseType::Toll, 'amount' => 1200],
            ],
        ],
        [
            'customer_name' => 'Reliance Retail DC',
            'start_at' => '2026-02-03 06:00:00',
            'end_at' => '2026-02-06 22:00:00',
            'status' => TripStatus::Completed,
            'route_legs' => [
                [
                    'from_city' => 'Nashik',
                    'to_city' => 'Surat',
                    'route_type' => RouteType::Loaded,
                    'sequence_no' => 1,
                    'distance_km' => 220,
                    'goods_name' => 'FMCG cartons',
                    'freight_amount' => 32000,
                    'advance_received' => 32000,
                ],
            ],
            'expenses' => [
                ['expense_type' => TripExpenseType::Diesel, 'amount' => 11000],
                ['expense_type' => TripExpenseType::DriverExpense, 'amount' => 1500],
            ],
        ],
        [
            'customer_name' => 'Mahindra Auto',
            'broker_name' => 'Patil Transport Agency',
            'start_at' => '2026-02-18 08:00:00',
            'end_at' => '2026-02-22 18:30:00',
            'status' => TripStatus::Completed,
            'route_legs' => [
                [
                    'from_city' => 'Pune',
                    'to_city' => 'Chennai',
                    'route_type' => RouteType::Loaded,
                    'sequence_no' => 1,
                    'distance_km' => 1180,
                    'goods_name' => 'Auto parts',
                    'freight_amount' => 95000,
                    'advance_received' => 40000,
                ],
                [
                    'from_city' => 'Chennai',
                    'to_city' => 'Bangalore',
                    'route_type' => RouteType::Empty,
                    'sequence_no' => 2,
                    'distance_km' => 350,
                ],
            ],
            'expenses' => [
                ['expense_type' => TripExpenseType::Diesel, 'amount' => 42000],
                ['expense_type' => TripExpenseType::Toll, 'amount' => 8500],
                ['expense_type' => TripExpenseType::FoodExpense, 'amount' => 2200],
            ],
        ],
        [
            'customer_name' => 'Local Cement Dealer',
            'start_at' => '2026-03-05 05:45:00',
            'end_at' => '2026-03-07 14:00:00',
            'status' => TripStatus::Completed,
            'route_legs' => [
                [
                    'from_city' => 'Ahmedabad',
                    'to_city' => 'Rajkot',
                    'route_type' => RouteType::Loaded,
                    'sequence_no' => 1,
                    'distance_km' => 215,
                    'goods_name' => 'Cement bags',
                    'freight_amount' => 18500,
                    'advance_received' => 0,
                ],
            ],
            'expenses' => [
                ['expense_type' => TripExpenseType::Diesel, 'amount' => 7200],
                ['expense_type' => TripExpenseType::LoadingCharges, 'amount' => 800],
            ],
        ],
        [
            'customer_name' => 'Flipkart Hub',
            'start_at' => '2026-04-10 09:15:00',
            'end_at' => '2026-04-13 21:00:00',
            'status' => TripStatus::Completed,
            'route_legs' => [
                [
                    'from_city' => 'Delhi',
                    'from_address' => 'Bawana Industrial Area',
                    'to_city' => 'Jaipur',
                    'route_type' => RouteType::Loaded,
                    'sequence_no' => 1,
                    'distance_km' => 280,
                    'goods_name' => 'E-commerce parcels',
                    'freight_amount' => 22000,
                    'advance_received' => 8000,
                ],
                [
                    'from_city' => 'Jaipur',
                    'to_city' => 'Delhi',
                    'route_type' => RouteType::Empty,
                    'sequence_no' => 2,
                    'distance_km' => 280,
                ],
            ],
            'expenses' => [
                ['expense_type' => TripExpenseType::Diesel, 'amount' => 9800],
                ['expense_type' => TripExpenseType::Toll, 'amount' => 2100],
            ],
        ],
        [
            'customer_name' => 'ITC Paper Mills',
            'start_at' => '2026-05-02 07:00:00',
            'end_at' => '2026-05-05 16:45:00',
            'status' => TripStatus::Completed,
            'route_legs' => [
                [
                    'from_city' => 'Kolkata',
                    'to_city' => 'Bhubaneswar',
                    'route_type' => RouteType::Loaded,
                    'sequence_no' => 1,
                    'distance_km' => 440,
                    'goods_name' => 'Paper rolls',
                    'freight_amount' => 41000,
                    'advance_received' => 15000,
                ],
            ],
            'expenses' => [
                ['expense_type' => TripExpenseType::Diesel, 'amount' => 16500],
                ['expense_type' => TripExpenseType::UnloadingCharges, 'amount' => 1200],
            ],
        ],
        [
            'customer_name' => 'UltraTech Plant',
            'broker_name' => 'Gupta & Sons',
            'start_at' => '2026-05-20 06:30:00',
            'end_at' => '2026-05-23 20:00:00',
            'status' => TripStatus::Completed,
            'route_legs' => [
                [
                    'from_city' => 'Indore',
                    'to_city' => 'Bhopal',
                    'route_type' => RouteType::Loaded,
                    'sequence_no' => 1,
                    'distance_km' => 195,
                    'goods_name' => 'Clinker',
                    'freight_amount' => 16800,
                    'advance_received' => 5000,
                ],
                [
                    'from_city' => 'Bhopal',
                    'to_city' => 'Indore',
                    'route_type' => RouteType::Empty,
                    'sequence_no' => 2,
                    'distance_km' => 195,
                ],
            ],
            'expenses' => [
                ['expense_type' => TripExpenseType::Diesel, 'amount' => 6400],
            ],
        ],
        [
            'customer_name' => 'Amazon FBA',
            'start_at' => '2026-06-01 08:00:00',
            'end_at' => null,
            'status' => TripStatus::InProgress,
            'route_legs' => [
                [
                    'from_city' => 'Hyderabad',
                    'to_city' => 'Bangalore',
                    'route_type' => RouteType::Loaded,
                    'sequence_no' => 1,
                    'distance_km' => 570,
                    'goods_name' => 'Consumer goods',
                    'freight_amount' => 48000,
                    'advance_received' => 12000,
                ],
            ],
            'expenses' => [
                ['expense_type' => TripExpenseType::Diesel, 'amount' => 14000],
                ['expense_type' => TripExpenseType::Toll, 'amount' => 3200],
            ],
        ],
        [
            'customer_name' => 'Pending Booking',
            'start_at' => '2026-06-10 07:00:00',
            'end_at' => null,
            'status' => TripStatus::Created,
            'route_legs' => [
                [
                    'from_city' => 'Mumbai',
                    'to_city' => 'Goa',
                    'route_type' => RouteType::Loaded,
                    'sequence_no' => 1,
                    'distance_km' => 590,
                    'goods_name' => 'Beverages',
                    'freight_amount' => 35000,
                    'advance_received' => 0,
                ],
            ],
            'expenses' => [],
        ],
        [
            'customer_name' => 'Cancelled Order',
            'start_at' => '2026-03-28 10:00:00',
            'end_at' => '2026-03-28 12:00:00',
            'status' => TripStatus::Cancelled,
            'route_legs' => [
                [
                    'from_city' => 'Vadodara',
                    'to_city' => 'Surat',
                    'route_type' => RouteType::Loaded,
                    'sequence_no' => 1,
                    'distance_km' => 165,
                    'freight_amount' => 12000,
                    'advance_received' => 0,
                ],
            ],
            'expenses' => [],
        ],
    ];

    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', self::DEFAULT_TENANT_SLUG)->first();

        if ($tenant === null) {
            $this->command?->error('Tenant not found. Run TestUserSeeder first.');

            return;
        }

        $admin = User::query()->where('email', self::ADMIN_EMAIL)->first();

        if ($admin === null) {
            $this->command?->error('Admin user not found. Run TestUserSeeder first.');

            return;
        }

        $vehicles = Vehicle::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('registration_number')
            ->get();

        if ($vehicles->isEmpty()) {
            $this->command?->warn('No vehicles found. Running DemoFleetDataSeeder…');
            $this->call(DemoFleetDataSeeder::class);
            $vehicles = Vehicle::query()
                ->where('tenant_id', $tenant->id)
                ->orderBy('registration_number')
                ->get();
        }

        if ($vehicles->count() < count(self::TRIP_DEFINITIONS)) {
            $this->command?->error('Not enough vehicles for demo trips. Run DemoFleetDataSeeder.');

            return;
        }

        TenantContext::setTenantId($tenant->id);

        $tripService = app(TripService::class);
        $created = 0;
        $skipped = 0;

        foreach (self::TRIP_DEFINITIONS as $index => $definition) {
            $vehicle = $vehicles->get($index);

            if ($vehicle === null) {
                break;
            }

            $driverId = $this->resolveDriverId($tenant->id, $vehicle->id);

            if ($driverId === null) {
                $this->command?->warn("Skipping trip {$index}: no driver for {$vehicle->registration_number}");
                $skipped++;

                continue;
            }

            if ($this->tripAlreadySeeded($tenant->id, $vehicle->id, (string) $definition['start_at'])) {
                $skipped++;

                continue;
            }

            $payload = [
                'tenant_id' => $tenant->id,
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driverId,
                'customer_name' => $definition['customer_name'],
                'broker_name' => $definition['broker_name'] ?? null,
                'start_at' => $definition['start_at'],
                'end_at' => $definition['end_at'],
                'status' => $definition['status']->value,
                'route_legs' => $this->normalizeRouteLegs($definition['route_legs']),
                'expenses' => $this->normalizeExpenses($definition['expenses'] ?? []),
            ];

            $tripService->create($payload, $admin->id);
            $created++;
        }

        TenantContext::setTenantId(null);

        $this->command?->info("Demo trips ready for [{$tenant->name}] (admin: ".self::ADMIN_EMAIL.')');
        $this->command?->table(
            ['Item', 'Count'],
            [
                ['Trips created', (string) $created],
                ['Trips skipped (already present)', (string) $skipped],
            ],
        );
    }

    private function tripAlreadySeeded(string $tenantId, string $vehicleId, string $startAt): bool
    {
        return Trip::query()
            ->where('tenant_id', $tenantId)
            ->where('vehicle_id', $vehicleId)
            ->where('start_at', $startAt)
            ->exists();
    }

    private function resolveDriverId(string $tenantId, string $vehicleId): ?string
    {
        $assignment = DriverAssignment::query()
            ->where('tenant_id', $tenantId)
            ->where('vehicle_id', $vehicleId)
            ->where('is_primary', true)
            ->whereNull('assigned_to')
            ->first();

        if ($assignment !== null) {
            return $assignment->driver_id;
        }

        return Driver::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->value('id');
    }

    /**
     * @param  list<array<string, mixed>>  $legs
     * @return list<array<string, mixed>>
     */
    private function normalizeRouteLegs(array $legs): array
    {
        return collect($legs)
            ->map(function (array $leg): array {
                $normalized = $leg;
                $routeType = $leg['route_type'] ?? RouteType::Loaded;

                $normalized['route_type'] = $routeType instanceof RouteType
                    ? $routeType->value
                    : (string) $routeType;

                return $normalized;
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $expenses
     * @return list<array<string, mixed>>
     */
    private function normalizeExpenses(array $expenses): array
    {
        return collect($expenses)
            ->map(function (array $expense): array {
                $normalized = $expense;
                $type = $expense['expense_type'] ?? TripExpenseType::Other;

                $normalized['expense_type'] = $type instanceof TripExpenseType
                    ? $type->value
                    : (string) $type;

                return $normalized;
            })
            ->values()
            ->all();
    }
}
