<?php

namespace Tests\Feature\Api;

use App\City;
use App\Customer;
use App\Driver;
use App\DriverAssignment;
use App\Enums\GstRegistrationType;
use App\Enums\TaxLiability;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Models\TenantSubscription;
use App\Modules\TripManagement\Services\TripService;
use App\State;
use App\Station;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Tenant;
use App\Trip;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripBiltyPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_created_trip_can_stream_bilty_pdf_without_freight(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createFixtures();
        $this->actingAs($user);

        $trip = $this->createTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ], complete: false);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->get("/app/api/trips/{$trip->id}/bilty.pdf");

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_bilty_pdf_includes_freight_when_requested(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createFixtures();
        $this->actingAs($user);

        $trip = $this->createTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 12500.5, 'to' => 'b'],
        ], complete: false);

        $groups = app(\App\Modules\TripManagement\Services\TripBiltyService::class)
            ->groupsForTrip($trip->fresh(['routeLegs.customer', 'vehicle', 'driver']));

        $this->assertSame(12500.5, (float) $groups[0]['freight_amount']);

        $withFreight = app(\App\Modules\TripManagement\Services\TripBiltyService::class)
            ->pdf($trip->fresh(['routeLegs.customer', 'vehicle', 'driver']), true)
            ->output();

        $withoutFreight = app(\App\Modules\TripManagement\Services\TripBiltyService::class)
            ->pdf($trip->fresh(['routeLegs.customer', 'vehicle', 'driver']), false)
            ->output();

        $this->assertNotSame($withFreight, $withoutFreight);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->get("/app/api/trips/{$trip->id}/bilty.pdf?show_freight=1")
            ->assertOk();
    }

    public function test_one_bilty_copy_per_revenue_leg_in_single_pdf(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createFixtures();
        $this->actingAs($user);

        $trip = $this->createTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
            ['customer' => $customers['a'], 'freight' => 5000, 'to' => 'c'],
            ['customer' => $customers['b'], 'freight' => 7000, 'to' => 'b'],
        ], complete: true);

        $groups = app(\App\Modules\TripManagement\Services\TripBiltyService::class)
            ->groupsForTrip($trip->fresh(['routeLegs.customer', 'vehicle', 'driver']));

        $this->assertCount(3, $groups);
        $this->assertSame(10000.0, (float) $groups[0]['freight_amount']);
        $this->assertSame(5000.0, (float) $groups[1]['freight_amount']);
        $this->assertSame(7000.0, (float) $groups[2]['freight_amount']);
        $this->assertSame(1, (int) $groups[0]['route_count']);
        $this->assertSame($customers['a']->id, (int) $groups[0]['customer_id']);
        $this->assertSame($customers['a']->id, (int) $groups[1]['customer_id']);
        $this->assertSame($customers['b']->id, (int) $groups[2]['customer_id']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->get("/app/api/trips/{$trip->id}/bilty.pdf?show_freight=1")
            ->assertOk();
    }

    public function test_bilty_uses_from_and_to_address_for_party_addresses(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createFixtures();
        $this->actingAs($user);

        $trip = $this->createTripWithLegs($tenant, $vehicle, $driver, $stations, [
            [
                'customer' => $customers['a'],
                'freight' => 10000,
                'to' => 'b',
                'consignor' => 'Sender Co',
                'consignee' => 'Receiver Co',
                'from_address' => 'Andheri East, Mumbai, Maharashtra',
                'to_address' => 'Hinjewadi, Pune, Maharashtra',
            ],
        ], complete: false);

        $groups = app(\App\Modules\TripManagement\Services\TripBiltyService::class)
            ->groupsForTrip($trip->fresh(['routeLegs.customer', 'vehicle', 'driver']));

        $this->assertSame('Sender Co', $groups[0]['consignor']);
        $this->assertSame('Andheri East, Mumbai, Maharashtra', $groups[0]['consignor_address']);
        $this->assertSame('Receiver Co', $groups[0]['consignee']);
        $this->assertSame('Hinjewadi, Pune, Maharashtra', $groups[0]['consignee_address']);
    }

    public function test_trip_without_revenue_customers_returns_validation_error(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createFixtures();
        $this->actingAs($user);

        $trip = $this->createTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ], complete: false);

        $trip->routeLegs()->update([
            'weight_ton' => 0,
            'freight_amount' => 0,
            'freight_rate' => 0,
            'customer_id' => null,
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('Accept', 'application/json')
            ->get("/app/api/trips/{$trip->id}/bilty.pdf")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trip']);
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle, 3: Driver, 4: array<string, Station>, 5: array{a: Customer, b: Customer}}
     */
    private function createFixtures(): array
    {
        $mh = State::query()->where('code', 'MH')->firstOrFail();

        $tenant = Tenant::query()->create([
            'name' => 'Bilty Tenant',
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
            'registration_type' => GstRegistrationType::Regular,
            'tax_liability' => TaxLiability::ForwardCharge,
            'gst_number' => '27AAAAA0000A1Z5',
            'pan_number' => 'ABCDE1234F',
            'state_id' => $mh->id,
            'gst_rate' => 5,
            'contact_person' => 'Admin',
            'contact_number' => '9876500001',
            'address' => 'Test Address',
        ]);

        TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'included_branches' => 10,
            'users_per_branch' => 3,
            'included_trucks' => 50,
            'included_lr_credits' => 0,
            'addon_branches' => 0,
            'addon_users' => 0,
            'addon_trucks' => 0,
            'addon_lr_credits' => 0,
            'lr_credits_remaining' => 0,
        ]);

        $operational = $this->operationalAttributesForTenant($tenant);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $operational['branch_id'],
            'name' => 'Bilty Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);

        $vehicle = Vehicle::query()->create([
            ...$operational,
            'registration_number' => 'MH12BLT001',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 1000,
        ]);

        $driver = Driver::query()->create([
            ...$operational,
            'name' => 'Bilty Driver',
            'phone' => '9876500099',
            'daily_wage' => 900,
            'is_active' => true,
        ]);

        DriverAssignment::query()->create([
            ...$operational,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => '2026-01-01',
            'is_primary' => true,
        ]);

        $cityA = City::query()->where('state_id', $mh->id)->where('name', 'Mumbai')->firstOrFail();
        $cityB = City::query()->where('state_id', $mh->id)->where('name', 'Pune')->firstOrFail();
        $cityC = City::query()->where('state_id', $mh->id)->where('name', 'Nagpur')->firstOrFail();

        $stations = [
            'home' => Station::query()->create([
                ...$operational,
                'country_id' => $mh->country_id,
                'state_id' => $mh->id,
                'city_id' => $cityA->id,
                'name' => 'Home Station',
            ]),
            'b' => Station::query()->create([
                ...$operational,
                'country_id' => $mh->country_id,
                'state_id' => $mh->id,
                'city_id' => $cityB->id,
                'name' => 'Station B',
            ]),
            'c' => Station::query()->create([
                ...$operational,
                'country_id' => $mh->country_id,
                'state_id' => $mh->id,
                'city_id' => $cityC->id,
                'name' => 'Station C',
            ]),
        ];

        $customers = [
            'a' => Customer::query()->create([
                ...$operational,
                'name' => 'Customer A',
                'country_id' => $mh->country_id,
                'state_id' => $mh->id,
                'city_id' => $cityA->id,
                'gst_number' => '27CCCCC0000C1Z5',
                'contact_number' => '9876500011',
            ]),
            'b' => Customer::query()->create([
                ...$operational,
                'name' => 'Customer B',
                'country_id' => $mh->country_id,
                'state_id' => $mh->id,
                'city_id' => $cityB->id,
                'gst_number' => '27DDDDD0000D1Z5',
                'contact_number' => '9876500012',
            ]),
        ];

        return [$tenant, $user, $vehicle, $driver, $stations, $customers];
    }

    /**
     * @param  array<string, Station>  $stations
     * @param  list<array{customer: Customer, freight: float, to: string}>  $legs
     */
    private function createTripWithLegs(
        Tenant $tenant,
        Vehicle $vehicle,
        Driver $driver,
        array $stations,
        array $legs,
        bool $complete = false,
    ): Trip {
        $routeLegs = [];
        $sequence = 1;

        foreach ($legs as $leg) {
            $toStation = $stations[$leg['to']];
            $weight = 10;
            $rate = $leg['freight'] / $weight;

            $routeLegs[] = [
                'sequence_no' => $sequence++,
                'customer_id' => $leg['customer']->id,
                'from_station_id' => $stations['home']->id,
                'to_station_id' => $toStation->id,
                'from_city' => $stations['home']->city?->name ?? 'Mumbai',
                'to_city' => $toStation->city?->name ?? 'Pune',
                'from_address' => $leg['from_address'] ?? null,
                'to_address' => $leg['to_address'] ?? null,
                'consignor' => $leg['consignor'] ?? null,
                'consignee' => $leg['consignee'] ?? null,
                'weight_ton' => $weight,
                'freight_rate' => $rate,
                'freight_amount' => $leg['freight'],
                'goods_name' => 'Goods',
                'distance_km' => 100,
            ];
        }

        $routeLegs[] = [
            'sequence_no' => $sequence,
            'customer_id' => $legs[0]['customer']->id,
            'from_station_id' => $stations[$legs[array_key_last($legs)]['to']]->id,
            'to_station_id' => $stations['home']->id,
            'from_city' => 'Return',
            'to_city' => 'Home',
            'weight_ton' => 0,
            'freight_rate' => 0,
            'freight_amount' => 0,
            'goods_name' => null,
            'distance_km' => 100,
        ];

        TenantContext::setTenantId($tenant->id);
        BranchContext::setBranchId((int) $vehicle->branch_id);

        $trip = app(TripService::class)->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'customer_id' => $legs[0]['customer']->id,
            'start_station_id' => $stations['home']->id,
            'trip_type' => TripType::RoundTrip->value,
            'start_at' => '2026-06-01 08:00:00',
            'end_at' => $complete ? '2026-06-05 20:00:00' : null,
            'actual_arrival_at' => $complete ? '2026-06-05 19:00:00' : null,
            'start_meter_reading_km' => 1000,
            'end_meter_reading_km' => $complete ? 1400 : null,
            'route_legs' => $routeLegs,
            'expenses' => [],
        ], null);

        if ($complete) {
            $trip->update([
                'status' => TripStatus::InProgress,
                'end_at' => '2026-06-05 20:00:00',
                'actual_arrival_at' => '2026-06-05 19:00:00',
                'end_meter_reading_km' => 1400,
            ]);
            $trip->update(['status' => TripStatus::Completed]);
        }

        return $trip->fresh(['routeLegs.customer', 'vehicle', 'driver']);
    }
}
