<?php

namespace Tests\Feature\Api;

use App\City;
use App\Customer;
use App\Driver;
use App\DriverAssignment;
use App\Enums\FreightBillingStatus;
use App\Enums\GstRegistrationType;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoicePaymentStatus;
use App\Enums\TaxLiability;
use App\Enums\TripStatus;
use App\Enums\TripType;
use App\Invoice;
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
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class InvoiceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    private function createSuperAdmin(): User
    {
        return User::query()->create([
            'tenant_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);
    }

    public function test_company_stores_renamed_gst_fields(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $stateId = State::query()->where('code', 'MH')->value('id');

        $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'GST Transport',
                'gst_number' => '27AAAAA0000A1Z5',
                'pan_number' => 'ABCDE1234F',
                'registration_type' => 'regular',
                'tax_liability' => 'forward_charge',
                'state_id' => $stateId,
                'gst_rate' => 5,
                'contact_person' => 'Owner',
                'contact_number' => '9876543210',
            ])
            ->assertCreated()
            ->assertJsonPath('data.registration_type', 'regular')
            ->assertJsonPath('data.tax_liability', 'forward_charge')
            ->assertJsonPath('data.state_id', $stateId)
            ->assertJsonPath('data.gst_rate', 5);
    }

    public function test_branch_can_inherit_or_override_company_gst(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $stateId = State::query()->where('code', 'MH')->value('id');

        $company = Tenant::query()->create([
            'owner_user_id' => $superAdmin->id,
            'name' => 'Branch GST Co',
            'slug' => 'branch-gst-co',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
            'registration_type' => GstRegistrationType::Regular,
            'tax_liability' => TaxLiability::ForwardCharge,
            'gst_number' => '27AAAAA0000A1Z5',
            'state_id' => $stateId,
            'gst_rate' => 5,
            'pan_number' => 'ABCDE1234F',
            'contact_person' => 'Owner',
            'contact_number' => '9876543200',
        ]);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $company->id,
                'branch_name' => 'Inherited Branch',
                'branch_contact_number' => '9876543211',
                'head_office' => true,
                'inherit_company_gst' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.inherit_company_gst', true);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $company->id,
                'branch_name' => 'Override Branch',
                'branch_contact_number' => '9876543212',
                'head_office' => false,
                'inherit_company_gst' => false,
                'registration_type' => 'composition',
                'gst_number' => null,
                'state_id' => $stateId,
            ])
            ->assertCreated()
            ->assertJsonPath('data.inherit_company_gst', false)
            ->assertJsonPath('data.registration_type', 'composition');
    }

    public function test_completed_trip_generates_one_invoice_per_customer_and_consolidates_same_customer(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
            ['customer' => $customers['a'], 'freight' => 5000, 'to' => 'c'],
            ['customer' => $customers['b'], 'freight' => 7000, 'to' => 'b'],
        ]);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [
                'due_date' => now()->addDays(10)->toDateString(),
            ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseCount('invoices', 2);

        $invoiceA = Invoice::query()->where('customer_id', $customers['a']->id)->first();
        $this->assertNotNull($invoiceA);
        $this->assertSame(15000.0, (float) $invoiceA->freight_amount);
        $this->assertSame(2, $invoiceA->items()->count());
        $this->assertSame(InvoiceDocumentType::TaxInvoice, $invoiceA->document_type);

        $invoiceB = Invoice::query()->where('customer_id', $customers['b']->id)->first();
        $this->assertNotNull($invoiceB);
        $this->assertSame(7000.0, (float) $invoiceB->freight_amount);
        $this->assertSame(1, $invoiceB->items()->count());

        $branchId = (int) $trip->branch_id;
        $year = (int) now()->format('Y');
        $numbers = collect([$invoiceA->invoice_number, $invoiceB->invoice_number])->sort()->values();
        $this->assertSame(
            [
                sprintf('INV-BR%03d-%d-0001', $branchId, $year),
                sprintf('INV-BR%03d-%d-0002', $branchId, $year),
            ],
            $numbers->all(),
        );

        $this->assertSame(
            FreightBillingStatus::Invoiced,
            $trip->fresh()->routeLegs->first()->billing_status
        );
    }

    public function test_invoice_preview_and_generate_honor_per_group_sequence_overrides(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
            ['customer' => $customers['b'], 'freight' => 7000, 'to' => 'b'],
        ]);

        $branchId = (int) $trip->branch_id;
        $invoiceDate = '2026-07-01';

        $preview = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/preview", [
                'groups' => [
                    (string) $customers['a']->id => [
                        'invoice_date' => $invoiceDate,
                        'invoice_sequence' => 50,
                    ],
                    (string) $customers['b']->id => [
                        'invoice_date' => $invoiceDate,
                        'invoice_sequence' => 51,
                    ],
                ],
            ])
            ->assertOk();

        $previewGroups = collect($preview->json('data'));
        $this->assertSame(
            sprintf('INV-BR%03d-2026-0050', $branchId),
            $previewGroups->firstWhere('customer_id', $customers['a']->id)['invoice_number'],
        );
        $this->assertSame(
            sprintf('INV-BR%03d-2026-0051', $branchId),
            $previewGroups->firstWhere('customer_id', $customers['b']->id)['invoice_number'],
        );

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [
                'groups' => [
                    (string) $customers['a']->id => [
                        'invoice_date' => $invoiceDate,
                        'invoice_sequence' => 50,
                    ],
                    (string) $customers['b']->id => [
                        'invoice_date' => $invoiceDate,
                        'invoice_sequence' => 51,
                    ],
                ],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customers['a']->id,
            'invoice_number' => sprintf('INV-BR%03d-2026-0050', $branchId),
        ]);
        $this->assertDatabaseHas('invoices', [
            'customer_id' => $customers['b']->id,
            'invoice_number' => sprintf('INV-BR%03d-2026-0051', $branchId),
        ]);
    }

    public function test_invoice_generation_rejects_duplicate_group_sequences(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
            ['customer' => $customers['b'], 'freight' => 7000, 'to' => 'b'],
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [
                'groups' => [
                    (string) $customers['a']->id => [
                        'invoice_date' => '2026-07-01',
                        'invoice_sequence' => 7,
                    ],
                    (string) $customers['b']->id => [
                        'invoice_date' => '2026-07-01',
                        'invoice_sequence' => 7,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['groups.'.$customers['b']->id.'.invoice_sequence']);
    }

    public function test_invoiced_trip_cannot_be_fully_edited_even_by_super_admin(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [
                'due_date' => now()->addDays(10)->toDateString(),
            ])
            ->assertCreated();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/app/api/trips/{$trip->id}")
            ->assertOk()
            ->assertJsonPath('data.has_invoices', true);

        $trip = $trip->fresh();
        $superAdmin = $this->createSuperAdmin();
        $tripService = app(TripService::class);

        try {
            $tripService->update($trip, ['notes' => 'Should not update'], $superAdmin);
            $this->fail('Expected ValidationException when editing an invoiced trip.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('trip', $exception->errors());
        }

        $updated = $tripService->update($trip, [
            'expenses' => [
                [
                    'expense_type' => 'TOLL',
                    'expense_date' => '2026-06-03',
                    'amount' => 500,
                ],
            ],
        ], $superAdmin);

        $this->assertSame(500.0, (float) $updated->total_expense_amount);
    }

    public function test_rejects_invoice_generation_for_non_completed_trip(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = app(TripService::class)->create($this->baseTripPayload($vehicle, $driver, $stations, $customers['a']));

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trip']);
    }

    public function test_rcm_invoice_allowed_without_customer_gst_and_charges_freight_only(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures(
            taxLiability: TaxLiability::ReverseCharge,
        );
        $this->actingAs($user);

        $customers['a']->forceFill(['gst_number' => null])->save();

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);

        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", []);

        $response->assertCreated();

        $invoice = Invoice::query()->first();
        $this->assertSame(InvoiceDocumentType::TaxInvoiceRcm, $invoice->document_type);
        $this->assertSame(10000.0, (float) $invoice->total_amount);
        $this->assertSame(500.0, (float) $invoice->gst_amount);
    }

    public function test_payment_updates_status_and_pdf_is_available(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);

        $generate = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [])
            ->assertCreated();

        $invoiceId = $generate->json('data.0.id');
        $total = (float) $generate->json('data.0.total_amount');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$invoiceId}/payments", [
                'amount' => round($total / 2, 2),
                'paid_at' => now()->toDateString(),
                'payment_method' => 'upi',
            ])
            ->assertCreated();

        $invoice = Invoice::query()->findOrFail($invoiceId);
        $this->assertSame(InvoicePaymentStatus::Partial, $invoice->payment_status);

        $paymentId = $invoice->payments()->firstOrFail()->id;

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson("/app/api/invoices/{$invoiceId}/payments/{$paymentId}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment']);

        $this->assertDatabaseHas('invoice_payments', [
            'id' => $paymentId,
            'invoice_id' => $invoiceId,
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->get("/app/api/invoices/{$invoiceId}/pdf")
            ->assertOk();
    }

    public function test_cancelled_invoice_remains_visible_and_read_only(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);

        $generate = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [])
            ->assertCreated();

        $invoiceId = $generate->json('data.0.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson("/app/api/invoices/{$invoiceId}")
            ->assertNoContent();

        $invoice = Invoice::query()->findOrFail($invoiceId);
        $this->assertSame(InvoicePaymentStatus::Cancelled, $invoice->payment_status);
        $this->assertFalse($invoice->trashed());
        $this->assertGreaterThan(0, $invoice->items()->count());
        $this->assertSame(
            FreightBillingStatus::Unbilled,
            $trip->fresh()->routeLegs->first()->billing_status
        );

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/app/api/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'cancelled')
            ->assertJsonPath('data.is_cancelled', true);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$invoiceId}/payments", [
                'amount' => 100,
                'paid_at' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson("/app/api/invoices/{$invoiceId}")
            ->assertForbidden();

        $this->assertFalse($trip->fresh()->hasInvoices());

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [])
            ->assertCreated()
            ->assertJsonCount(1, 'data');

        $this->assertSame(2, Invoice::query()->where('trip_id', $trip->id)->count());
        $this->assertSame(1, Invoice::query()->where('trip_id', $trip->id)->where('payment_status', InvoicePaymentStatus::Cancelled->value)->count());
        $this->assertTrue($trip->fresh()->hasInvoices());
    }

    public function test_due_reminder_command_creates_app_notifications(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);

        $generate = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [
                'due_date' => now()->subDay()->toDateString(),
            ])
            ->assertCreated();

        $invoiceId = $generate->json('data.0.id');

        Artisan::call('invoices:send-due-reminders');

        $this->assertDatabaseHas('app_notifications', [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'type' => 'invoice_due_reminder',
        ]);

        $this->assertTrue(
            \App\AppNotification::query()
                ->withoutGlobalScopes()
                ->where('data->invoice_id', $invoiceId)
                ->exists()
        );
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle, 3: Driver, 4: array<string, Station>, 5: array{a: Customer, b: Customer}}
     */
    private function createInvoiceFixtures(
        TaxLiability $taxLiability = TaxLiability::ForwardCharge,
    ): array {
        $mh = State::query()->where('code', 'MH')->firstOrFail();
        $gj = State::query()->where('code', 'GJ')->firstOrFail();

        $tenant = Tenant::query()->create([
            'name' => 'Invoice Tenant',
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
            'registration_type' => GstRegistrationType::Regular,
            'tax_liability' => $taxLiability,
            'gst_number' => '27AAAAA0000A1Z5',
            'pan_number' => 'ABCDE1234F',
            'state_id' => $mh->id,
            'gst_rate' => 5,
            'contact_person' => 'Admin',
            'contact_number' => '9876500001',
        ]);

        $operational = $this->operationalAttributesForTenant($tenant);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $operational['branch_id'],
            'name' => 'Invoice Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);

        $vehicle = Vehicle::query()->create([
            ...$operational,
            'registration_number' => 'MH12INV001',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 1000,
        ]);

        $driver = Driver::query()->create([
            ...$operational,
            'name' => 'Invoice Driver',
            'phone' => '9876500002',
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

        // Return home for round trip validation if needed
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

    /**
     * @param  array<string, Station>  $stations
     * @return array<string, mixed>
     */
    private function baseTripPayload(Vehicle $vehicle, Driver $driver, array $stations, Customer $customer): array
    {
        return [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'start_station_id' => $stations['home']->id,
            'trip_type' => TripType::RoundTrip->value,
            'start_at' => '2026-06-01 08:00:00',
            'start_meter_reading_km' => 1000,
            'route_legs' => [
                [
                    'sequence_no' => 1,
                    'customer_id' => $customer->id,
                    'from_station_id' => $stations['home']->id,
                    'to_station_id' => $stations['b']->id,
                    'weight_ton' => 10,
                    'freight_rate' => 1000,
                    'freight_amount' => 10000,
                    'goods_name' => 'Goods',
                    'distance_km' => 100,
                ],
                [
                    'sequence_no' => 2,
                    'customer_id' => $customer->id,
                    'from_station_id' => $stations['b']->id,
                    'to_station_id' => $stations['home']->id,
                    'weight_ton' => 0,
                    'freight_rate' => 0,
                    'freight_amount' => 0,
                    'distance_km' => 100,
                ],
            ],
            'expenses' => [],
        ];
    }
}
