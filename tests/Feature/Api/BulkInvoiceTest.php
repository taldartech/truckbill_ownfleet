<?php

namespace Tests\Feature\Api;

use App\City;
use App\Customer;
use App\Driver;
use App\DriverAssignment;
use App\Enums\FreightBillingStatus;
use App\Enums\GstRegistrationType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\TaxLiability;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Invoice;
use App\Models\TenantSubscription;
use App\Modules\TripManagement\Services\TripService;
use App\State;
use App\Station;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Tenant;
use App\Trip;
use App\TripRouteLeg;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_eligible_trips_lists_only_customer_unbilled_legs(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $tripMixed = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
            ['customer' => $customers['b'], 'freight' => 7000, 'to' => 'c'],
        ]);

        $tripBOnly = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['b'], 'freight' => 5000, 'to' => 'b'],
        ]);

        $tripInProgress = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 4000, 'to' => 'b'],
        ]);
        $tripInProgress->update(['status' => TripStatus::InProgress]);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/app/api/invoices/eligible-trips?customer_id='.$customers['a']->id)
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($tripMixed->id, $ids);
        $this->assertNotContains($tripBOnly->id, $ids);
        $this->assertNotContains($tripInProgress->id, $ids);

        $mixedRow = collect($response->json('data'))->firstWhere('id', $tripMixed->id);
        $this->assertEquals(10000.0, (float) $mixedRow['unbilled_freight_amount']);
        $this->assertSame(1, $mixedRow['unbilled_leg_count']);
    }

    public function test_bulk_generate_creates_one_invoice_and_marks_only_customer_legs(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $tripOne = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
            ['customer' => $customers['b'], 'freight' => 7000, 'to' => 'c'],
        ]);

        $tripTwo = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 5000, 'to' => 'b'],
        ]);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/app/api/invoices/bulk', [
                'customer_id' => $customers['a']->id,
                'trip_ids' => [$tripOne->id, $tripTwo->id],
                'particulars_mode' => 'combined',
            ])
            ->assertCreated();

        $invoiceId = $response->json('data.id');
        $this->assertNotNull($invoiceId);
        $this->assertNull($response->json('data.trip_id'));
        $this->assertCount(2, $response->json('data.trips'));
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_trips', 2);
        $this->assertDatabaseHas('invoice_trips', [
            'invoice_id' => $invoiceId,
            'trip_id' => $tripOne->id,
        ]);
        $this->assertDatabaseHas('invoice_trips', [
            'invoice_id' => $invoiceId,
            'trip_id' => $tripTwo->id,
        ]);

        $this->assertEquals(
            FreightBillingStatus::Invoiced,
            TripRouteLeg::query()
                ->where('trip_id', $tripOne->id)
                ->where('customer_id', $customers['a']->id)
                ->where('freight_amount', '>', 0)
                ->first()
                ->billing_status,
        );

        $this->assertEquals(
            FreightBillingStatus::Unbilled,
            TripRouteLeg::query()
                ->where('trip_id', $tripOne->id)
                ->where('customer_id', $customers['b']->id)
                ->where('freight_amount', '>', 0)
                ->first()
                ->billing_status,
        );

        $tripOne->refresh();
        $tripTwo->refresh();
        $this->assertSame(TripStatus::Completed, $tripOne->status);
        $this->assertSame(TripStatus::Invoiced, $tripTwo->status);
    }

    public function test_bulk_generate_marks_trip_invoiced_when_no_unbilled_legs_remain(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 8000, 'to' => 'b'],
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/app/api/invoices/bulk', [
                'customer_id' => $customers['a']->id,
                'trip_ids' => [$trip->id],
            ])
            ->assertCreated();

        $this->assertSame(TripStatus::Invoiced, $trip->fresh()->status);
    }

    public function test_bulk_generate_rejects_empty_selection_and_forbidden_without_permission(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 5000, 'to' => 'b'],
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/app/api/invoices/bulk', [
                'customer_id' => $customers['a']->id,
                'trip_ids' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trip_ids']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/app/api/invoices/bulk', [
                'customer_id' => $customers['a']->id,
                'trip_ids' => [$trip->id],
                'due_date' => '2020-01-01',
                'invoice_date' => '2026-06-01',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['due_date']);

        $viewer = User::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $user->branch_id,
            'name' => 'Viewer',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::ACCOUNTANT,
        ]);

        $this->actingAs($viewer)
            ->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/app/api/invoices/bulk', [
                'customer_id' => $customers['a']->id,
                'trip_ids' => [$trip->id],
            ])
            ->assertForbidden();
    }

    public function test_cancel_bulk_invoice_unbills_legs_and_reverts_trip_status(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $tripOne = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);
        $tripTwo = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 5000, 'to' => 'b'],
        ]);

        $invoiceId = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/app/api/invoices/bulk', [
                'customer_id' => $customers['a']->id,
                'trip_ids' => [$tripOne->id, $tripTwo->id],
            ])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame(TripStatus::Invoiced, $tripOne->fresh()->status);
        $this->assertSame(TripStatus::Invoiced, $tripTwo->fresh()->status);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson("/app/api/invoices/{$invoiceId}")
            ->assertNoContent();

        $invoice = Invoice::query()->findOrFail($invoiceId);
        $this->assertSame(InvoicePaymentStatus::Cancelled, $invoice->payment_status);

        $this->assertEquals(
            FreightBillingStatus::Unbilled,
            TripRouteLeg::query()
                ->where('trip_id', $tripOne->id)
                ->where('customer_id', $customers['a']->id)
                ->where('freight_amount', '>', 0)
                ->first()
                ->billing_status,
        );

        $this->assertSame(TripStatus::Completed, $tripOne->fresh()->status);
        $this->assertSame(TripStatus::Completed, $tripTwo->fresh()->status);
    }

    public function test_single_trip_generate_still_works_and_populates_pivot(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [])
            ->assertCreated()
            ->assertJsonCount(1, 'data');

        $invoiceId = $response->json('data.0.id');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'trip_id' => $trip->id,
        ]);
        $this->assertDatabaseHas('invoice_trips', [
            'invoice_id' => $invoiceId,
            'trip_id' => $trip->id,
        ]);
        $this->assertSame(TripStatus::Invoiced, $trip->fresh()->status);
    }

    public function test_bulk_preview_returns_totals_for_selected_trips(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $tripOne = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);
        $tripTwo = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 5000, 'to' => 'b'],
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/app/api/invoices/bulk/preview', [
                'customer_id' => $customers['a']->id,
                'trip_ids' => [$tripOne->id, $tripTwo->id],
                'particulars_mode' => 'combined',
            ])
            ->assertOk()
            ->assertJsonPath('data.route_freight_amount', 15000)
            ->assertJsonPath('data.customer_id', $customers['a']->id)
            ->assertJsonCount(2, 'data.invoice_lines');
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle, 3: Driver, 4: array<string, Station>, 5: array<string, Customer>}
     */
    private function createInvoiceFixtures(): array
    {
        $mh = State::query()->where('code', 'MH')->firstOrFail();
        $gj = State::query()->where('code', 'GJ')->firstOrFail();

        $tenant = Tenant::query()->create([
            'name' => 'Bulk Invoice Tenant',
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
            'name' => 'Bulk Invoice Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);

        $vehicle = Vehicle::query()->create([
            ...$operational,
            'registration_number' => 'MH12BULK01',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 1000,
        ]);

        $driver = Driver::query()->create([
            ...$operational,
            'name' => 'Bulk Invoice Driver',
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
                'country_id' => $gj->country_id,
                'state_id' => $gj->id,
                'city_id' => City::query()->where('state_id', $gj->id)->value('id'),
                'gst_number' => '24DDDDD0000D1Z5',
                'contact_number' => '9876500012',
            ]),
        ];

        return [$tenant, $user, $vehicle, $driver, $stations, $customers];
    }

    /**
     * @param  array<string, Station>  $stations
     * @param  list<array{customer: Customer, freight: float, to: string}>  $legs
     */
    private function createCompletedTripWithLegs(
        Tenant $tenant,
        Vehicle $vehicle,
        Driver $driver,
        array $stations,
        array $legs,
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

        $payload = [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'customer_id' => $legs[0]['customer']->id,
            'start_station_id' => $stations['home']->id,
            'trip_type' => TripType::RoundTrip->value,
            'start_at' => '2026-06-01 08:00:00',
            'end_at' => '2026-06-05 20:00:00',
            'actual_arrival_at' => '2026-06-05 19:00:00',
            'start_meter_reading_km' => 1000,
            'end_meter_reading_km' => 1400,
            'route_legs' => $routeLegs,
            'expenses' => [],
        ];

        TenantContext::setTenantId($tenant->id);
        BranchContext::setBranchId((int) $vehicle->branch_id);

        $trip = app(TripService::class)->create($payload, null);

        $trip->update([
            'status' => TripStatus::InProgress,
            'end_at' => '2026-06-05 20:00:00',
            'actual_arrival_at' => '2026-06-05 19:00:00',
            'end_meter_reading_km' => 1400,
        ]);
        $trip->update(['status' => TripStatus::Completed]);

        return $trip->fresh(['routeLegs']);
    }
}
