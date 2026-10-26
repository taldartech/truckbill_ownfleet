<?php

namespace Tests\Feature\Api;

use App\Challan;
use App\Driver;
use App\Emi;
use App\Enums\ChallanPaymentStatus;
use App\Jobs\SendComplianceReminderJob;
use App\Models\TenantSubscription;
use App\Support\TruckTypeDefaults;
use App\Support\TyreLayoutDefaults;
use App\Tenant;
use App\TruckType;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComplianceAndEmiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    private function createTenantUser(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Fleet Logistics '.fake()->company(),
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'included_branches' => 10,
            'users_per_branch' => 5,
            'included_trucks' => 100,
            'included_lr_credits' => 0,
            'addon_branches' => 0,
            'addon_users' => 0,
            'addon_trucks' => 0,
            'addon_lr_credits' => 0,
            'lr_credits_remaining' => 0,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor($role) ?? UserRole::ADMIN,
        ]);

        return [$tenant, $this->assignBranchIfRequired($user, $tenant)];
    }

    private function createVehicle(Tenant $tenant): Vehicle
    {
        TyreLayoutDefaults::seedForTenant($tenant->id);
        TruckTypeDefaults::seedForTenant($tenant->id);

        return Vehicle::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'registration_number' => 'MH12AB'.fake()->unique()->numerify('####'),
            'vehicle_type' => 'TRUCK',
            'make' => 'Tata',
            'model' => 'Prima',
            'odometer_km' => 5000,
            'ownership_type' => 'OWNED',
            'is_active' => true,
        ]);
    }

    private function createDriver(Tenant $tenant): Driver
    {
        return Driver::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'name' => 'Ramesh Kumar',
            'phone' => fake()->unique()->numerify('98765#####'),
            'license_number' => 'DL-'.fake()->unique()->numerify('##########'),
            'license_expiry' => now()->addYears(3)->toDateString(),
            'is_active' => true,
        ]);
    }

    private function grantChallanPermissions(string $role): void
    {
        $roleId = UserRole::idFor($role);

        foreach ([
            'challans.view' => 'View challans',
            'challans.manage' => 'Manage challans',
            'challans.delete' => 'Delete challans',
        ] as $slug => $name) {
            $permission = DB::table('permissions')->where('slug', $slug)->first();
            if ($permission) {
                $permissionId = $permission->id;
            } else {
                $permissionId = DB::table('permissions')->insertGetId([
                    'slug' => $slug,
                    'name' => $name,
                    'group' => 'compliance',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('permission_user_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'user_role_id' => $roleId,
            ]);
        }
    }

        public function test_admin_can_view_compliance_index_when_permissions_exist(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->grantChallanPermissions('admin');

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/compliance');

        $response->assertOk()
            ->assertJsonStructure([
                'challans' => ['data'],
                'emis' => ['data'],
            ]);
    }

    public function test_admin_can_store_and_retrieve_emi(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);

        $payload = [
            'vehicle_id' => (string) $vehicle->id,
            'lender_name' => 'HDFC Bank Fleet Finance',
            'emi_amount' => 35000.00,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'DUE',
        ];

        Sanctum::actingAs($admin);
        $response = $this->postJson('/api/compliance/emis', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('lender_name', 'HDFC Bank Fleet Finance')
            ->assertJsonPath('emi_amount', 35000);

        $this->assertDatabaseHas('emis', [
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'lender_name' => 'HDFC Bank Fleet Finance',
            'status' => 'DUE',
        ]);
    }

    public function test_admin_can_store_and_manage_challan(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $this->grantChallanPermissions('admin');
        $vehicle = $this->createVehicle($tenant);
        $driver = $this->createDriver($tenant);

        $payload = [
            'vehicle_id' => (string) $vehicle->id,
            'driver_id' => (string) $driver->id,
            'challan_number' => 'CH-MH12-99881',
            'challan_date' => now()->toDateString(),
            'violation_type' => 'Overloading at Toll Plaza',
            'amount' => 5000.00,
            'status' => ChallanPaymentStatus::Unpaid->value,
        ];

        Sanctum::actingAs($admin);

        // Store via Challan API
        $response = $this->postJson('/api/challans', $payload);
        $response->assertStatus(201)
            ->assertJsonPath('data.challan_number', 'CH-MH12-99881');

        $challanId = $response->json('data.id');

        // Show single challan
        $showResponse = $this->getJson("/api/challans/{$challanId}");
        $showResponse->assertOk()
            ->assertJsonPath('data.violation_type', 'Overloading at Toll Plaza');

        // Delete challan with reason
        $deleteResponse = $this->deleteJson("/api/challans/{$challanId}", [
            'deleted_reason' => 'Duplicate entry cancelled by RTO court',
        ]);
        $deleteResponse->assertNoContent();

        $this->assertSoftDeleted('challans', [
            'id' => $challanId,
        ]);
    }

    public function test_moderator_cannot_view_or_create_compliance_or_emis(): void
    {
        [$tenant, $moderator] = $this->createTenantUser('moderator');
        $vehicle = $this->createVehicle($tenant);

        Sanctum::actingAs($moderator);

        // Moderator attempt to view compliance
        $this->getJson('/api/compliance')
            ->assertForbidden();

        // Moderator attempt to create EMI
        $this->postJson('/api/compliance/emis', [
            'vehicle_id' => (string) $vehicle->id,
            'lender_name' => 'ICICI Bank',
            'emi_amount' => 20000,
            'due_date' => now()->toDateString(),
        ])
            ->assertForbidden();
    }

    public function test_cross_tenant_isolation_on_compliance_and_emis(): void
    {
        [$tenantA, $adminA] = $this->createTenantUser('admin');
        [$tenantB, $adminB] = $this->createTenantUser('admin');
        $this->grantChallanPermissions('admin');

        $vehicleA = $this->createVehicle($tenantA);

        $emiA = Emi::query()->create([
            'tenant_id' => $tenantA->id,
            'vehicle_id' => $vehicleA->id,
            'lender_name' => 'Tenant A Private Bank',
            'emi_amount' => 45000,
            'due_date' => now()->addDays(2)->toDateString(),
            'status' => 'DUE',
        ]);

        // Tenant B requests compliance index
        Sanctum::actingAs($adminB);
        $responseB = $this->getJson('/api/compliance');
        $responseB->assertOk();

        // Must NOT see Tenant A's EMI
        $emiIds = collect($responseB->json('emis.data'))->pluck('id');
        $this->assertFalse($emiIds->contains($emiA->id));
    }

    public function test_check_emi_due_command_dispatches_reminders_only_for_due_emis(): void
    {
        Queue::fake();

        [$tenant, $admin] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);

        // Due in 2 days (should trigger reminder)
        $dueEmi = Emi::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'lender_name' => 'Axis Bank',
            'emi_amount' => 25000,
            'due_date' => now()->addDays(2)->toDateString(),
            'status' => 'DUE',
        ]);

        // Due in 10 days (should NOT trigger reminder)
        $futureEmi = Emi::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'lender_name' => 'Tata Capital',
            'emi_amount' => 30000,
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'DUE',
        ]);

        // Already paid (should NOT trigger reminder)
        $paidEmi = Emi::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'lender_name' => 'SBI Fleet Loan',
            'emi_amount' => 20000,
            'due_date' => now()->addDays(1)->toDateString(),
            'status' => 'PAID',
        ]);

        // Set tenant context so TenantScoped query resolves in test context
        \App\Support\TenantContext::setTenantId($tenant->id);

        try {
            $exitCode = Artisan::call('compliance:check-emi-due');
            $this->assertSame(0, $exitCode);

            // Assert job was dispatched for $dueEmi only
            Queue::assertPushed(SendComplianceReminderJob::class, 1);
        } finally {
            \App\Support\TenantContext::setTenantId(null);
        }
    }

    public function test_check_challan_pending_command_queues_reminders(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);

        Challan::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'challan_number' => 'CH-TEST-PENDING-001',
            'challan_date' => now()->toDateString(),
            'amount' => 1500,
            'status' => ChallanPaymentStatus::Unpaid->value,
        ]);

        Challan::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'challan_number' => 'CH-TEST-PAID-001',
            'challan_date' => now()->toDateString(),
            'amount' => 500,
            'status' => ChallanPaymentStatus::Paid->value,
        ]);

        Queue::fake();

        \App\Support\TenantContext::setTenantId($tenant->id);

        try {
            $exitCode = Artisan::call('compliance:check-challan-pending');
            $this->assertSame(0, $exitCode);
            $this->assertStringContainsString('Queued 1 challan reminders.', Artisan::output());
            Queue::assertPushed(SendComplianceReminderJob::class, 1);
        } finally {
            \App\Support\TenantContext::setTenantId(null);
        }
    }

    public function test_check_document_expiry_command_processes_documents(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        $vehicle = $this->createVehicle($tenant);

        \App\VehicleDocument::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'document_type' => 'fitness_certificate',
            'document_number' => 'FC-EXP-001',
            'expiry_date' => now()->addDays(5)->toDateString(),
            'reminder_days_before' => 30,
        ]);

        \App\VehicleDocument::query()->create([
            'tenant_id' => $tenant->id,
            'vehicle_id' => $vehicle->id,
            'document_type' => 'insurance',
            'document_number' => 'INS-EXP-FAR',
            'expiry_date' => now()->addDays(90)->toDateString(),
            'reminder_days_before' => 30,
        ]);

        $exitCode = Artisan::call('compliance:check-document-expiry');
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Processed 1 documents.', Artisan::output());
    }
}
