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
use App\InvoiceAuditLog;
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
        $this->provisionActiveSubscription($company);

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
        $this->assertSame(TripStatus::Invoiced, $trip->fresh()->status);

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

    public function test_invoice_generation_combined_particulars_merges_routes_into_one_line(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
            ['customer' => $customers['a'], 'freight' => 5000, 'to' => 'c'],
        ]);

        $preview = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/preview", [
                'groups' => [
                    (string) $customers['a']->id => [
                        'particulars_mode' => 'combined',
                    ],
                ],
            ])
            ->assertOk();

        $previewGroup = collect($preview->json('data'))->firstWhere('customer_id', $customers['a']->id);
        $this->assertSame('combined', $previewGroup['particulars_mode']);
        $this->assertCount(1, $previewGroup['invoice_lines']);
        $this->assertSame(15000.0, (float) $previewGroup['invoice_lines'][0]['freight_amount']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [
                'groups' => [
                    (string) $customers['a']->id => [
                        'particulars_mode' => 'combined',
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonCount(1, 'data');

        $invoice = Invoice::query()->where('customer_id', $customers['a']->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('combined', $invoice->particulars_mode->value);
        $this->assertSame(15000.0, (float) $invoice->freight_amount);
        $this->assertSame(1, $invoice->items()->whereNotNull('trip_route_leg_id')->count());
        $this->assertSame(15000.0, (float) $invoice->items()->first()->freight_amount);

        foreach ($previewGroup['route_leg_ids'] as $legId) {
            $leg = $trip->fresh()->routeLegs->firstWhere('id', $legId);
            $this->assertNotNull($leg);
            $this->assertSame(FreightBillingStatus::Invoiced, $leg->billing_status);
        }
    }

    public function test_combined_invoice_cancel_unbills_all_customer_routes_on_trip(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
            ['customer' => $customers['a'], 'freight' => 5000, 'to' => 'c'],
        ]);

        $generate = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [
                'groups' => [
                    (string) $customers['a']->id => [
                        'particulars_mode' => 'combined',
                    ],
                ],
            ])
            ->assertCreated();

        $invoiceId = $generate->json('data.0.id');
        $legIds = $trip->fresh()->routeLegs
            ->filter(
                fn ($leg) => (string) $leg->customer_id === (string) $customers['a']->id
                    && (float) $leg->freight_amount > 0,
            )
            ->pluck('id')
            ->all();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson("/app/api/invoices/{$invoiceId}")
            ->assertNoContent();

        foreach ($legIds as $legId) {
            $leg = $trip->fresh()->routeLegs->firstWhere('id', $legId);
            $this->assertNotNull($leg);
            $this->assertSame(FreightBillingStatus::Unbilled, $leg->billing_status);
        }
    }

    public function test_combined_invoice_pdf_lists_all_route_consignors_and_consignees(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
            ['customer' => $customers['a'], 'freight' => 5000, 'to' => 'c'],
        ]);

        $revenueLegs = $trip->routeLegs
            ->filter(fn ($leg) => (float) $leg->freight_amount > 0)
            ->sortBy('sequence_no')
            ->values();

        $revenueLegs[0]->update([
            'consignor' => 'Alpha Corp',
            'consignee' => 'Beta Ltd',
            'from_address' => 'Warehouse A, Mumbai',
            'to_address' => 'Depot B, Pune',
        ]);
        $revenueLegs[1]->update([
            'consignor' => 'Gamma Corp',
            'consignee' => 'Delta Ltd',
            'from_address' => 'Warehouse C, Nashik',
            'to_address' => 'Depot D, Nagpur',
        ]);

        $generate = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [
                'groups' => [
                    (string) $customers['a']->id => [
                        'particulars_mode' => 'combined',
                    ],
                ],
            ])
            ->assertCreated();

        $invoice = Invoice::query()
            ->with([
                'customer.state',
                'customer.city',
                'customerState',
                'supplierState',
                'items.routeLeg.fromStation.city',
                'items.routeLeg.fromStation.state',
                'items.routeLeg.toStation.city',
                'items.routeLeg.toStation.state',
                'trip.routeLegs.fromStation.city',
                'trip.routeLegs.fromStation.state',
                'trip.routeLegs.toStation.city',
                'trip.routeLegs.toStation.state',
                'trip.vehicle',
                'trip.driver',
                'branch.company.state',
                'tenant.state',
            ])
            ->findOrFail($generate->json('data.0.id'));

        $firstItem = $invoice->items->first();
        $this->assertNotNull($firstItem);
        $this->assertIsArray($firstItem->route_legs_snapshot);
        $this->assertCount(2, $firstItem->route_legs_snapshot);

        $snapshotConsignors = collect($firstItem->route_legs_snapshot)
            ->pluck('consignor')
            ->filter()
            ->values()
            ->all();

        $snapshotConsignees = collect($firstItem->route_legs_snapshot)
            ->pluck('consignee')
            ->filter()
            ->values()
            ->all();

        $this->assertContains('Alpha Corp', $snapshotConsignors);
        $this->assertContains('Gamma Corp', $snapshotConsignors);
        $this->assertContains('Beta Ltd', $snapshotConsignees);
        $this->assertContains('Delta Ltd', $snapshotConsignees);

        $html = view('invoices.pdf.party-bill-pdf-modern', [
            'invoice' => $invoice,
            'company' => $invoice->branch?->company ?? $invoice->tenant,
            'document_color' => $tenant->documentColor(),
            'document_text_color' => $tenant->documentTextColor() ?? '#000000',
            'document_color_soft' => $tenant->documentColorWithOpacity(0.2),
        ])->render();

        $this->assertStringContainsString('Alpha Corp', $html);
        $this->assertStringContainsString('Gamma Corp', $html);
        $this->assertStringContainsString('Beta Ltd', $html);
        $this->assertStringContainsString('Delta Ltd', $html);
    }

    public function test_combined_invoice_pdf_deduplicates_identical_consignors_and_consignees(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
            ['customer' => $customers['a'], 'freight' => 5000, 'to' => 'c'],
        ]);

        $revenueLegs = $trip->routeLegs
            ->filter(fn ($leg) => (float) $leg->freight_amount > 0)
            ->sortBy('sequence_no')
            ->values();

        $sharedConsignor = 'Shared Consignor, Warehouse A, Mumbai';
        $sharedConsignee = 'Shared Consignee, Depot B, Pune';

        $revenueLegs[0]->update([
            'consignor' => 'Shared Consignor',
            'consignee' => 'Shared Consignee',
            'from_address' => 'Warehouse A, Mumbai',
            'to_address' => 'Depot B, Pune',
        ]);
        $revenueLegs[1]->update([
            'consignor' => 'Shared Consignor',
            'consignee' => 'Shared Consignee',
            'from_address' => 'Warehouse A, Mumbai',
            'to_address' => 'Depot B, Pune',
        ]);

        $generate = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [
                'groups' => [
                    (string) $customers['a']->id => [
                        'particulars_mode' => 'combined',
                    ],
                ],
            ])
            ->assertCreated();

        $invoice = Invoice::query()
            ->with([
                'customer.state',
                'customer.city',
                'customerState',
                'supplierState',
                'items.routeLeg.fromStation.city',
                'items.routeLeg.fromStation.state',
                'items.routeLeg.toStation.city',
                'items.routeLeg.toStation.state',
                'trip.routeLegs.fromStation.city',
                'trip.routeLegs.fromStation.state',
                'trip.routeLegs.toStation.city',
                'trip.routeLegs.toStation.state',
                'trip.vehicle',
                'trip.driver',
                'branch.company.state',
                'tenant.state',
            ])
            ->findOrFail($generate->json('data.0.id'));

        $html = view('invoices.pdf.party-bill-pdf-modern', [
            'invoice' => $invoice,
            'company' => $invoice->branch?->company ?? $invoice->tenant,
            'document_color' => $tenant->documentColor(),
            'document_text_color' => $tenant->documentTextColor() ?? '#000000',
            'document_color_soft' => $tenant->documentColorWithOpacity(0.2),
        ])->render();

        $this->assertSame(1, substr_count($html, $sharedConsignor));
        $this->assertSame(1, substr_count($html, $sharedConsignee));
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

    public function test_invoice_preview_includes_custom_items_in_taxable_base_and_gst(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);

        $preview = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/preview", [
                'groups' => [
                    (string) $customers['a']->id => [
                        'custom_items' => [
                            ['description' => 'Loading charges', 'amount' => 1500],
                            ['description' => 'Detention', 'amount' => 500],
                        ],
                    ],
                ],
            ])
            ->assertOk();

        $group = collect($preview->json('data'))->firstWhere('customer_id', $customers['a']->id);

        $this->assertSame(10000.0, (float) $group['route_freight_amount']);
        $this->assertSame(2000.0, (float) $group['custom_amount']);
        $this->assertSame(12000.0, (float) $group['freight_amount']);
        $this->assertSame(600.0, (float) $group['gst_amount']);
        $this->assertSame(12600.0, (float) $group['total_amount']);
        $this->assertCount(2, $group['custom_items']);
    }

    public function test_invoice_generate_persists_custom_items_and_updates_trip_profit(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);
        $trip->forceFill(['total_expense_amount' => 3000])->save();

        $generate = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [
                'groups' => [
                    (string) $customers['a']->id => [
                        'custom_items' => [
                            ['description' => 'Loading charges', 'amount' => 1500],
                            ['description' => 'Detention', 'amount' => 500],
                        ],
                    ],
                ],
            ])
            ->assertCreated();

        $invoiceId = $generate->json('data.0.id');
        $invoice = Invoice::query()->with('items')->findOrFail($invoiceId);

        $this->assertSame(12000.0, (float) $invoice->freight_amount);
        $this->assertSame(3, $invoice->items->count());

        $customItems = $invoice->items->whereNull('trip_route_leg_id')->values();
        $this->assertCount(2, $customItems);
        $this->assertSame('Loading charges', $customItems[0]->goods_name);
        $this->assertSame(1500.0, (float) $customItems[0]->freight_amount);
        $this->assertSame('Detention', $customItems[1]->goods_name);
        $this->assertSame(500.0, (float) $customItems[1]->freight_amount);

        $trip = $trip->fresh();
        $this->assertSame(2000.0, (float) $trip->total_custom_amount);
        $this->assertSame(10000.0, (float) $trip->total_freight_amount);
        $this->assertSame(9000.0, $trip->profitAmount());

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/app/api/trips/{$trip->id}")
            ->assertOk()
            ->assertJsonPath('data.total_custom_amount', '2000.00')
            ->assertJsonPath('data.profit_amount', 9000);
    }

    public function test_cancelling_invoice_clears_custom_amount_from_trip(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);
        $trip->forceFill(['total_expense_amount' => 3000])->save();

        $generate = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [
                'groups' => [
                    (string) $customers['a']->id => [
                        'custom_items' => [
                            ['description' => 'Loading charges', 'amount' => 2000],
                        ],
                    ],
                ],
            ])
            ->assertCreated();

        $invoiceId = $generate->json('data.0.id');
        $this->assertSame(9000.0, $trip->fresh()->profitAmount());

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson("/app/api/invoices/{$invoiceId}")
            ->assertNoContent();

        $trip = $trip->fresh();
        $this->assertSame(0.0, (float) $trip->total_custom_amount);
        $this->assertSame(7000.0, $trip->profitAmount());
    }

    public function test_custom_items_validation_rejects_blank_description_and_non_positive_amount(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [
                'groups' => [
                    (string) $customers['a']->id => [
                        'custom_items' => [
                            ['description' => '', 'amount' => 100],
                            ['description' => 'Valid', 'amount' => 0],
                        ],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'groups.'.$customers['a']->id.'.custom_items.0.description',
                'groups.'.$customers['a']->id.'.custom_items.1.amount',
            ]);
    }

    public function test_pending_invoice_can_be_updated_with_additional_charges(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);

        $generate = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [
                'groups' => [
                    (string) $customers['a']->id => [
                        'custom_items' => [
                            ['description' => 'Loading charges', 'amount' => 1500],
                        ],
                    ],
                ],
            ])
            ->assertCreated();

        $invoiceId = $generate->json('data.0.id');

        $update = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/app/api/invoices/{$invoiceId}", [
                'custom_items' => [
                    ['description' => 'Loading charges', 'amount' => 2000],
                    ['description' => 'Detention', 'amount' => 500],
                ],
            ])
            ->assertOk();

        $update->assertJsonPath('data.invoice_number', $generate->json('data.0.invoice_number'));
        $update->assertJsonPath('data.route_freight_amount', 10000);
        $update->assertJsonPath('data.custom_amount', 2500);
        $update->assertJsonPath('data.freight_amount', 12500);
        $update->assertJsonPath('data.gst_amount', 625);
        $update->assertJsonPath('data.total_amount', 13125);
        $update->assertJsonPath('data.can_edit_charges', true);

        $invoice = Invoice::query()->with('items')->findOrFail($invoiceId);
        $this->assertSame(3, $invoice->items->count());
        $this->assertSame(10000.0, (float) $invoice->items->whereNotNull('trip_route_leg_id')->sum('freight_amount'));
        $this->assertSame(2500.0, (float) $invoice->items->whereNull('trip_route_leg_id')->sum('freight_amount'));
        $this->assertSame(2500.0, (float) $trip->fresh()->total_custom_amount);

        $this->assertDatabaseHas('invoice_audit_logs', [
            'invoice_id' => $invoiceId,
            'event_type' => 'charges_updated',
        ]);
    }

    public function test_non_pending_invoice_cannot_be_updated_via_api(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);

        $generate = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate")
            ->assertCreated();

        $invoiceId = $generate->json('data.0.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$invoiceId}/payments", [
                'amount' => 5000,
                'paid_at' => now()->toDateString(),
                'payment_method' => 'cash',
            ])
            ->assertCreated();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/app/api/invoices/{$invoiceId}", [
                'custom_items' => [
                    ['description' => 'Loading charges', 'amount' => 2000],
                ],
            ])
            ->assertForbidden();
    }

    public function test_cancelled_invoice_cannot_be_updated_via_api(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);

        $generate = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate")
            ->assertCreated();

        $invoiceId = $generate->json('data.0.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson("/app/api/invoices/{$invoiceId}")
            ->assertNoContent();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/app/api/invoices/{$invoiceId}", [
                'custom_items' => [
                    ['description' => 'Loading charges', 'amount' => 2000],
                ],
            ])
            ->assertForbidden();
    }

    public function test_pending_invoice_update_can_remove_all_additional_charges(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);

        $generate = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [
                'groups' => [
                    (string) $customers['a']->id => [
                        'custom_items' => [
                            ['description' => 'Loading charges', 'amount' => 1500],
                        ],
                    ],
                ],
            ])
            ->assertCreated();

        $invoiceId = $generate->json('data.0.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/app/api/invoices/{$invoiceId}", [
                'custom_items' => [],
            ])
            ->assertOk()
            ->assertJsonPath('data.custom_amount', 0)
            ->assertJsonPath('data.freight_amount', 10000)
            ->assertJsonPath('data.total_amount', 10500);

        $invoice = Invoice::query()->with('items')->findOrFail($invoiceId);
        $this->assertCount(1, $invoice->items);
        $this->assertSame(0.0, (float) $trip->fresh()->total_custom_amount);
        $this->assertSame(1, InvoiceAuditLog::query()->where('invoice_id', $invoiceId)->count());
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

        $tripAfterCancel = $trip->fresh();
        $this->assertFalse($tripAfterCancel->hasInvoices());
        $this->assertSame(TripStatus::Completed, $tripAfterCancel->status);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/app/api/trips/{$trip->id}")
            ->assertOk()
            ->assertJsonPath('data.has_invoices', false)
            ->assertJsonPath('data.can_generate_invoices', true)
            ->assertJsonPath('data.status', TripStatus::Completed->value);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [])
            ->assertCreated()
            ->assertJsonCount(1, 'data');

        $this->assertSame(2, Invoice::query()->where('trip_id', $trip->id)->count());
        $this->assertSame(1, Invoice::query()->where('trip_id', $trip->id)->where('payment_status', InvoicePaymentStatus::Cancelled->value)->count());
        $this->assertTrue($trip->fresh()->hasInvoices());
    }

    public function test_soft_deleting_latest_invoice_frees_number_and_hides_invoice(): void
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
        $invoiceNumber = $generate->json('data.0.invoice_number');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/app/api/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.can_delete', true);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$invoiceId}/delete")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['deletion_reason']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$invoiceId}/delete", [
                'deletion_reason' => 'Created by mistake during testing.',
            ])
            ->assertNoContent();

        $this->assertSoftDeleted('invoices', ['id' => $invoiceId]);
        $this->assertNull(Invoice::query()->find($invoiceId));

        $deleted = Invoice::withTrashed()->findOrFail($invoiceId);
        $this->assertStringStartsWith($invoiceNumber.'__deleted_', $deleted->invoice_number);
        $this->assertSame('Created by mistake during testing.', $deleted->deletion_reason);
        $this->assertSame($user->id, $deleted->deleted_by);
        $this->assertSame(
            FreightBillingStatus::Unbilled,
            $trip->fresh()->routeLegs->first()->billing_status
        );
        $this->assertSame(TripStatus::Completed, $trip->fresh()->status);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/app/api/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.is_archived', true)
            ->assertJsonPath('data.invoice_number', $invoiceNumber)
            ->assertJsonPath('data.deletion_reason', 'Created by mistake during testing.');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/app/api/invoices?archived=archived')
            ->assertOk()
            ->assertJsonPath('data.0.id', $invoiceId)
            ->assertJsonPath('data.0.invoice_number', $invoiceNumber)
            ->assertJsonPath('data.0.deletion_reason', 'Created by mistake during testing.');

        $regenerate = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [])
            ->assertCreated();

        $this->assertSame($invoiceNumber, $regenerate->json('data.0.invoice_number'));
        $this->assertSame(1, Invoice::query()->where('trip_id', $trip->id)->count());
    }

    public function test_active_invoice_list_excludes_archived_invoices(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);

        $invoiceId = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [])
            ->assertCreated()
            ->json('data.0.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$invoiceId}/delete", [
                'deletion_reason' => 'Hidden from active invoice list after delete.',
            ])
            ->assertNoContent();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/app/api/invoices')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/app/api/invoices?archived=archived')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $invoiceId)
            ->assertJsonPath('data.0.is_archived', true);
    }

    public function test_only_latest_invoice_can_be_soft_deleted(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $firstTrip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 8000, 'to' => 'b'],
        ]);
        $secondTrip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 9000, 'to' => 'b'],
        ]);

        $firstInvoiceId = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$firstTrip->id}/invoices/generate", [])
            ->assertCreated()
            ->json('data.0.id');

        $secondInvoiceId = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$secondTrip->id}/invoices/generate", [])
            ->assertCreated()
            ->json('data.0.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/app/api/invoices/{$firstInvoiceId}")
            ->assertOk()
            ->assertJsonPath('data.can_delete', false);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$firstInvoiceId}/delete", [
                'deletion_reason' => 'Older invoice should not delete first.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$secondInvoiceId}/delete", [
                'deletion_reason' => 'Removing latest invoice first as required.',
            ])
            ->assertNoContent();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/app/api/invoices/{$firstInvoiceId}")
            ->assertOk()
            ->assertJsonPath('data.can_delete', true);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$firstInvoiceId}/delete", [
                'deletion_reason' => 'Now the previous invoice is latest and removable.',
            ])
            ->assertNoContent();
    }

    public function test_cannot_soft_delete_invoice_with_payments(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 10000, 'to' => 'b'],
        ]);

        $invoiceId = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate", [])
            ->assertCreated()
            ->json('data.0.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$invoiceId}/payments", [
                'amount' => 100,
                'paid_at' => now()->toDateString(),
            ])
            ->assertCreated();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/app/api/invoices/{$invoiceId}")
            ->assertOk()
            ->assertJsonPath('data.can_delete', false);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$invoiceId}/delete", [
                'deletion_reason' => 'Should not delete invoice after payment.',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invoice']);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoiceId,
            'deleted_at' => null,
        ]);
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

    public function test_broker_customer_invoice_skips_gst_for_regular_company(): void
    {
        [$tenant, $user, $vehicle, $driver, $stations, $customers] = $this->createInvoiceFixtures();
        $this->actingAs($user);

        $customers['a']->update(['is_broker' => true]);

        $trip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a']->fresh(), 'freight' => 10000, 'to' => 'b'],
        ]);

        $preview = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/preview")
            ->assertOk();

        $group = collect($preview->json('data'))->firstWhere('customer_id', $customers['a']->id);
        $this->assertNotNull($group);
        $this->assertTrue($group['is_broker']);
        $this->assertSame(InvoiceDocumentType::BillInvoice->value, $group['document_type']);
        $this->assertFalse($group['displays_gst']);
        $this->assertFalse($group['collects_gst']);
        $this->assertSame(0, (int) $group['gst_amount']);
        $this->assertSame(10000, (int) $group['total_amount']);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$trip->id}/invoices/generate")
            ->assertCreated();

        $invoice = Invoice::query()->where('customer_id', $customers['a']->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceDocumentType::BillInvoice, $invoice->document_type);
        $this->assertSame(0.0, (float) $invoice->gst_amount);
        $this->assertSame(10000.0, (float) $invoice->freight_amount);
        $this->assertSame(10000.0, (float) $invoice->total_amount);
    }

    public function test_customer_is_broker_flag_can_be_set_and_updated(): void
    {
        [$tenant, $user] = array_slice($this->createInvoiceFixtures(), 0, 2);
        $this->actingAs($user);

        $mh = State::query()->where('code', 'MH')->firstOrFail();
        $cityId = City::query()->where('state_id', $mh->id)->where('name', 'Mumbai')->value('id');

        $create = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/app/api/customers', [
                'name' => 'Broker Customer',
                'state_id' => $mh->id,
                'city_id' => $cityId,
                'contact_number' => '9876543299',
                'is_broker' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_broker', true);

        $customerId = $create->json('data.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson('/app/api/customers/'.$customerId, [
                'name' => 'Broker Customer',
                'state_id' => $mh->id,
                'city_id' => $cityId,
                'contact_number' => '9876543299',
                'is_broker' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_broker', false);
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

    public function test_record_payment_with_tds_and_claims_deductions(): void
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
        $this->assertEquals(10500.00, $total);

        // Remit 10,000 + TDS 200 (2% on freight) + Shortage Claim 300 = Total Settled 10,500 (Full settlement)
        $response = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$invoiceId}/payments", [
                'amount' => 10000.00,
                'tds_amount' => 200.00,
                'deduction_amount' => 300.00,
                'deduction_reason' => 'Transit shortage 2 bags',
                'paid_at' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
                'notes' => 'Settled with TDS and shortage',
            ])
            ->assertCreated();

        $response->assertJsonPath('data.amount', 10000)
            ->assertJsonPath('data.tds_amount', 200)
            ->assertJsonPath('data.deduction_amount', 300)
            ->assertJsonPath('data.deduction_reason', 'Transit shortage 2 bags')
            ->assertJsonPath('data.total_settled_amount', 10500);

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoiceId,
            'amount' => 10000.00,
            'tds_amount' => 200.00,
            'deduction_amount' => 300.00,
            'deduction_reason' => 'Transit shortage 2 bags',
        ]);

        $invoice = Invoice::query()->findOrFail($invoiceId);
        $this->assertSame(InvoicePaymentStatus::Paid, $invoice->payment_status);
        $this->assertEquals(10500.00, (float) $invoice->paid_amount);
        $this->assertEquals(0.00, (float) $invoice->remainingAmount());

        // Also verify InvoiceResource returns payments with TDS and deductions
        $showResponse = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/app/api/invoices/{$invoiceId}")
            ->assertOk();

        $showResponse->assertJsonPath('data.paid_amount', 10500)
            ->assertJsonPath('data.remaining_amount', 0)
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.payments.0.amount', 10000)
            ->assertJsonPath('data.payments.0.tds_amount', 200)
            ->assertJsonPath('data.payments.0.deduction_amount', 300)
            ->assertJsonPath('data.payments.0.deduction_reason', 'Transit shortage 2 bags')
            ->assertJsonPath('data.payments.0.total_settled_amount', 10500);
    }

    public function test_record_payment_validation_for_tds_and_deductions(): void
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

        // Negative TDS amount rejected
        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$invoiceId}/payments", [
                'amount' => 5000,
                'tds_amount' => -100,
                'paid_at' => now()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tds_amount']);

        // Negative deduction amount rejected
        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$invoiceId}/payments", [
                'amount' => 5000,
                'deduction_amount' => -50,
                'paid_at' => now()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['deduction_amount']);

        // Deduction amount without deduction_reason rejected
        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$invoiceId}/payments", [
                'amount' => 5000,
                'deduction_amount' => 200,
                'deduction_reason' => '',
                'paid_at' => now()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['deduction_reason']);

        // Total settlement exceeds balance rejected (total is 10500, attempting 10000 + 1000 = 11000)
        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$invoiceId}/payments", [
                'amount' => 10000,
                'tds_amount' => 1000,
                'paid_at' => now()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        // Zero total settlement rejected
        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$invoiceId}/payments", [
                'amount' => 0,
                'tds_amount' => 0,
                'deduction_amount' => 0,
                'paid_at' => now()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_smart_cumulative_tds_tracking_across_multi_part_payments(): void
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

        // Payment 1: Partial payment with 2% TDS (₹200) + ₹5,000 received = ₹5,200 cleared
        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$invoiceId}/payments", [
                'amount' => 5000,
                'tds_amount' => 200,
                'paid_at' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
            ])
            ->assertCreated();

        $invoiceAfterP1 = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/app/api/invoices/{$invoiceId}")
            ->assertOk();

        $invoiceAfterP1->assertJsonPath('data.payment_status', 'partial')
            ->assertJsonPath('data.paid_amount', 5200)
            ->assertJsonPath('data.remaining_amount', 5300)
            ->assertJsonPath('data.total_tds_deducted', 200);

        // Payment 2: Customer pays remaining ₹5,300 with 0 TDS (since TDS was already deducted in payment 1)
        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$invoiceId}/payments", [
                'amount' => 5300,
                'tds_amount' => 0,
                'paid_at' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
            ])
            ->assertCreated();

        $invoiceAfterP2 = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson("/app/api/invoices/{$invoiceId}")
            ->assertOk();

        $invoiceAfterP2->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.paid_amount', 10500)
            ->assertJsonPath('data.remaining_amount', 0)
            ->assertJsonPath('data.total_tds_deducted', 200);

        // Verify excessive TDS exceeding freight base is rejected
        $secondTrip = $this->createCompletedTripWithLegs($tenant, $vehicle, $driver, $stations, [
            ['customer' => $customers['a'], 'freight' => 5000, 'to' => 'b'],
        ]);
        $secondInvoiceId = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/trips/{$secondTrip->id}/invoices/generate", [])
            ->assertCreated()
            ->json('data.0.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson("/app/api/invoices/{$secondInvoiceId}/payments", [
                'amount' => 0,
                'tds_amount' => 6000,
                'paid_at' => now()->toDateString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tds_amount']);
    }
}
