<?php

namespace Tests\Feature\Api;

use App\Branch;
use App\Models\TenantSubscription;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_super_admin_can_manage_branches_for_owned_companies(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Branch Co');

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $tenant->id,
                'branch_name' => 'Head Office',
                'branch_contact_number' => '9876543210',
                'branch_address' => 'Main Road',
                'head_office' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.branch_name', 'Head Office')
            ->assertJsonPath('data.head_office', true);

        $branchId = Branch::query()->value('id');

        $this->actingAs($superAdmin)
            ->getJson('/app/api/branches?company_id='.$tenant->id)
            ->assertOk()
            ->assertJsonPath('data.0.branch_name', 'Head Office');

        $this->actingAs($superAdmin)
            ->putJson('/app/api/branches/'.$branchId, [
                'branch_name' => 'HQ Updated',
            ])
            ->assertOk()
            ->assertJsonPath('data.branch_name', 'HQ Updated');

        $this->actingAs($superAdmin)
            ->deleteJson('/app/api/branches/'.$branchId, [
                'deleted_reason' => 'Branch closed',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Branch deleted.');

        $this->assertSoftDeleted('branches', ['id' => $branchId]);
        $deletedBranch = \App\Branch::withTrashed()->findOrFail($branchId);
        $this->assertSame($superAdmin->id, $deletedBranch->deleted_by);
        $this->assertEquals($superAdmin->id, $deletedBranch->deletedBy?->id);
    }

    public function test_branch_deletion_impact_returns_truck_and_driver_counts(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Impact Co');
        $branch = Branch::query()->create([
            'company_id' => $tenant->id,
            'branch_name' => 'Impact Branch',
            'branch_contact_number' => '9876543211',
            'head_office' => true,
        ]);

        \App\Vehicle::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'branch_id' => $branch->id,
            'registration_number' => 'MH12IMPACT',
            'vehicle_type' => 'TRUCK',
            'make' => 'Tata',
            'model' => 'Ultra',
            'odometer_km' => 100,
            'is_active' => true,
        ]);

        \App\Driver::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'branch_id' => $branch->id,
            'name' => 'Impact Driver',
            'phone' => '9888777666',
            'license_number' => 'DL-IMPACT',
            'daily_wage' => 500,
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin)
            ->getJson('/app/api/branches/'.$branch->id.'/deletion-impact')
            ->assertOk()
            ->assertJsonPath('trucks_count', 1)
            ->assertJsonPath('drivers_count', 1)
            ->assertJsonPath('users_count', 0);
    }

    public function test_deleting_branch_clears_assigned_user_branch_id(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'User Unassign Co');
        $branch = Branch::query()->create([
            'company_id' => $tenant->id,
            'branch_name' => 'User Branch',
            'branch_contact_number' => '9876543212',
            'head_office' => true,
        ]);

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Branch Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);

        $this->actingAs($superAdmin)
            ->deleteJson('/app/api/branches/'.$branch->id, [
                'deleted_reason' => 'Branch closed',
            ])
            ->assertOk();

        $this->assertSoftDeleted('branches', ['id' => $branch->id]);
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'branch_id' => null,
        ]);
    }

    public function test_super_admin_cannot_manage_branches_for_other_companies(): void
    {
        $owner = $this->createSuperAdmin();
        $otherOwner = $this->createSuperAdmin();
        $foreignTenant = $this->createOwnedTenant($otherOwner, 'Foreign Co');

        $this->actingAs($owner)
            ->postJson('/app/api/branches', [
                'company_id' => $foreignTenant->id,
                'branch_name' => 'Illegal Branch',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);
    }

    public function test_only_one_head_office_allowed_per_company(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Single HQ Co');

        Branch::query()->create([
            'company_id' => $tenant->id,
            'branch_name' => 'Existing HQ',
            'branch_contact_number' => '9876543210',
            'head_office' => true,
        ]);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $tenant->id,
                'branch_name' => 'Second HQ',
                'branch_contact_number' => '9876543211',
                'head_office' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['head_office']);
    }

    public function test_branch_cannot_use_another_companys_contact_number(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $companyA = $this->createOwnedTenant($superAdmin, 'Company Alpha', [
            'short_name' => 'CALPHA',
            'contact_number' => '9999999999',
        ]);
        $companyB = $this->createOwnedTenant($superAdmin, 'Company Beta', [
            'short_name' => 'CBETA',
            'contact_number' => '9983921134',
        ]);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $companyA->id,
                'branch_name' => 'A Branch',
                'branch_contact_number' => '9983921134',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_contact_number']);
    }

    public function test_unique_branch_number_cannot_be_reused(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $companyA = $this->createOwnedTenant($superAdmin, 'Company Alpha', [
            'short_name' => 'CALPHA',
            'contact_number' => '9999999999',
        ]);
        $companyB = $this->createOwnedTenant($superAdmin, 'Company Beta', [
            'short_name' => 'CBETA',
            'contact_number' => '9983921134',
        ]);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $companyA->id,
                'branch_name' => 'A Branch One',
                'branch_contact_number' => '9983922334',
            ])
            ->assertCreated();

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $companyA->id,
                'branch_name' => 'A Branch Two',
                'branch_contact_number' => '9983922334',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_contact_number']);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $companyB->id,
                'branch_name' => 'B Branch',
                'branch_contact_number' => '9983922334',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_contact_number']);
    }

    public function test_deleted_branch_name_can_be_reused_for_same_company(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Reuse Name Co');

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $tenant->id,
                'branch_name' => 'test-1',
                'branch_contact_number' => '9876543210',
                'head_office' => true,
            ])
            ->assertCreated();

        $branchId = Branch::query()->value('id');

        $this->actingAs($superAdmin)
            ->deleteJson('/app/api/branches/'.$branchId, [
                'deleted_reason' => 'Branch closed',
            ])
            ->assertOk();

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $tenant->id,
                'branch_name' => 'test-1',
                'branch_contact_number' => '9876543211',
                'head_office' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.branch_name', 'test-1');
    }

    public function test_branches_under_same_company_can_share_contact_number(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Shared Number Co', [
            'contact_number' => '9999999999',
        ]);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $tenant->id,
                'branch_name' => 'Branch One',
                'branch_contact_number' => '9999999999',
            ])
            ->assertCreated();

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $tenant->id,
                'branch_name' => 'Branch Two',
                'branch_contact_number' => '9999999999',
            ])
            ->assertCreated();
    }

    public function test_branch_contact_number_is_required(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Required Contact Co');

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $tenant->id,
                'branch_name' => 'No Contact Branch',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_contact_number']);
    }

    public function test_branch_contact_number_must_be_ten_digits(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Validation Co');

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $tenant->id,
                'branch_name' => 'Invalid Contact Branch',
                'branch_contact_number' => '998392113',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_contact_number']);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $tenant->id,
                'branch_name' => 'Valid Contact Branch',
                'branch_contact_number' => '99 839 211 34',
            ])
            ->assertCreated()
            ->assertJsonPath('data.branch_contact_number', '9983921134');
    }

    public function test_branch_creation_is_blocked_when_subscription_branch_limit_reached(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Limited Co');
        $tenant->subscription->update([
            'included_branches' => 2,
            'addon_branches' => 0,
        ]);

        Branch::query()->create([
            'company_id' => $tenant->id,
            'branch_name' => 'Branch One',
            'branch_contact_number' => '9876543210',
            'head_office' => true,
        ]);

        Branch::query()->create([
            'company_id' => $tenant->id,
            'branch_name' => 'Branch Two',
            'branch_contact_number' => '9876543211',
        ]);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $tenant->id,
                'branch_name' => 'Branch Three',
                'branch_contact_number' => '9876543212',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['company_id']);
    }

    public function test_admin_cannot_access_branch_management(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Admin Co',
            'slug' => 'admin-co',
            'short_name' => 'AC',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);

        $this->actingAs($admin)
            ->getJson('/app/api/branches')
            ->assertForbidden();
    }

    public function test_branch_number_settings_can_be_set_on_create(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Sequence Co');

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $tenant->id,
                'branch_name' => 'Sequence Branch',
                'branch_contact_number' => '9876543212',
                'head_office' => true,
                'number_settings' => [
                    'trip_initial' => 'TRIP-X',
                    'trip_separator_1' => '-',
                    'trip_year_type' => 'year',
                    'trip_year_digits' => 4,
                    'trip_separator_2' => '-',
                    'trip_sequence_digits' => 4,
                    'invoice_initial' => 'INV-X',
                    'invoice_separator_1' => '-',
                    'invoice_year_type' => 'blank',
                    'invoice_year_digits' => 4,
                    'invoice_separator_2' => '-',
                    'invoice_sequence_digits' => 3,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.number_settings.trip_initial', 'TRIP-X')
            ->assertJsonPath('data.number_settings.invoice_year_type', 'blank')
            ->assertJsonPath('data.number_settings.invoice_sequence_digits', 3);
    }

    public function test_branch_number_settings_can_change_after_trips_exist(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Locked Sequence Co');
        $branch = $this->createBranchForTenant($tenant, [
            'branch_name' => 'Locked Branch',
            'branch_contact_number' => '9876543213',
            'head_office' => true,
        ]);

        $vehicle = \App\Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'registration_number' => 'MH12LOCKED',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 1000,
        ]);

        $driver = \App\Driver::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'name' => 'Locked Driver',
            'phone' => '9876543299',
            'daily_wage' => 900,
            'is_active' => true,
        ]);

        \App\Trip::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'trip_number' => sprintf('TRIP-BR%03d-2026-10000', $branch->id),
            'status' => 'PENDING',
        ]);

        $this->actingAs($superAdmin)
            ->putJson('/app/api/branches/'.$branch->id, [
                'number_settings' => [
                    'trip_initial' => 'NEWTRIP',
                    'trip_separator_1' => '/',
                    'trip_year_type' => 'year',
                    'trip_year_digits' => 2,
                    'trip_separator_2' => '/',
                    'trip_sequence_digits' => 5,
                    'invoice_initial' => 'NEWINV',
                    'invoice_separator_1' => '-',
                    'invoice_year_type' => 'year',
                    'invoice_year_digits' => 4,
                    'invoice_separator_2' => '-',
                    'invoice_sequence_digits' => 4,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.number_settings.trip_initial', 'NEWTRIP')
            ->assertJsonPath('data.number_settings.trip_year_digits', 2);
    }

    public function test_branch_can_use_company_logo_or_own_logo(): void
    {
        Storage::fake('public');

        $superAdmin = $this->createSuperAdmin();
        $companyLogo = UploadedFile::fake()->image('company.png', 100, 100);

        $companyResponse = $this->actingAs($superAdmin)
            ->post('/app/api/companies', [
                'name' => 'Branch Logo Co',
                'pan_number' => 'DDDDD1234D',
                'contact_person' => 'Branch Contact',
                'contact_number' => '9876543293',
                'company_logo' => $companyLogo,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertCreated();

        $companyId = $companyResponse->json('data.id');
        $companyLogoPath = $companyResponse->json('data.company_logo');

        $inheritResponse = $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $companyId,
                'branch_name' => 'Inherit Logo Branch',
                'branch_contact_number' => '9876543294',
                'head_office' => true,
                'use_company_logo' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.use_company_logo', true)
            ->assertJsonPath('data.branch_logo', null);

        $inheritBranch = Branch::query()->findOrFail($inheritResponse->json('data.id'));
        $this->assertSame(
            Storage::disk('public')->url($companyLogoPath),
            $inheritBranch->logoPublicUrl(),
        );

        $branchLogo = UploadedFile::fake()->image('branch.png', 110, 110);

        $customResponse = $this->actingAs($superAdmin)
            ->post('/app/api/branches', [
                'company_id' => $companyId,
                'branch_name' => 'Custom Logo Branch',
                'branch_contact_number' => '9876543295',
                'head_office' => false,
                'use_company_logo' => '0',
                'branch_logo' => $branchLogo,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertCreated()
            ->assertJsonPath('data.use_company_logo', false);

        $customLogoPath = $customResponse->json('data.branch_logo');
        $this->assertNotNull($customLogoPath);
        Storage::disk('public')->assertExists($customLogoPath);

        $customBranchId = $customResponse->json('data.id');

        $this->actingAs($superAdmin)
            ->putJson('/app/api/branches/'.$customBranchId, [
                'use_company_logo' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.use_company_logo', true)
            ->assertJsonPath('data.branch_logo', null);

        Storage::disk('public')->assertMissing($customLogoPath);
    }

    public function test_branch_update_keeps_existing_logo_when_no_file_is_provided(): void
    {
        Storage::fake('public');

        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Keep Branch Logo Co');
        $branchLogo = UploadedFile::fake()->image('keep-branch.png', 100, 100);

        $createResponse = $this->actingAs($superAdmin)
            ->post('/app/api/branches', [
                'company_id' => $tenant->id,
                'branch_name' => 'Keep Logo Branch',
                'branch_contact_number' => '9876543296',
                'head_office' => true,
                'use_company_logo' => '0',
                'branch_logo' => $branchLogo,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertCreated();

        $branchId = $createResponse->json('data.id');
        $storedLogo = $createResponse->json('data.branch_logo');

        $this->actingAs($superAdmin)
            ->putJson('/app/api/branches/'.$branchId, [
                'branch_name' => 'Keep Logo Branch Updated',
                'use_company_logo' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.branch_name', 'Keep Logo Branch Updated')
            ->assertJsonPath('data.branch_logo', $storedLogo);

        Storage::disk('public')->assertExists($storedLogo);

        $this->actingAs($superAdmin)
            ->post('/app/api/branches/'.$branchId, [
                '_method' => 'PUT',
                'use_company_logo' => '0',
                'remove_logo' => '1',
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('data.branch_logo', null);

        Storage::disk('public')->assertMissing($storedLogo);
    }

    public function test_backfill_branch_data_command(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Backfill Tenant');

        $vehicle = \App\Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => null,
            'registration_number' => 'MH12BF0001',
            'vehicle_type' => 'TRUCK',
        ]);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('branches:backfill-data', ['--dry-run' => true]);
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Dry run complete.', \Illuminate\Support\Facades\Artisan::output());
        $this->assertNull($vehicle->fresh()->branch_id);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('branches:backfill-data');
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Branch backfill complete.', \Illuminate\Support\Facades\Artisan::output());

        $defaultBranch = Branch::query()->where('company_id', $tenant->id)->first();
        $this->assertNotNull($defaultBranch);
        $this->assertSame($defaultBranch->id, $vehicle->fresh()->branch_id);
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

    private function createOwnedTenant(User $owner, string $name, array $attributes = []): Tenant
    {
        $tenant = Tenant::query()->create(array_merge([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'short_name' => str($name)->upper()->substr(0, 8)->toString(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ], $attributes));

        $this->createActiveSubscription($tenant);

        return $tenant;
    }

    private function createActiveSubscription(Tenant $tenant, array $attributes = []): TenantSubscription
    {
        return TenantSubscription::query()->create(array_merge([
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
        ], $attributes));
    }
}
