<?php

namespace Tests\Feature\Api;

use App\Branch;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->deleteJson('/app/api/branches/'.$branchId)
            ->assertOk()
            ->assertJsonPath('message', 'Branch deleted.');

        $this->assertSoftDeleted('branches', ['id' => $branchId]);
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
            ->deleteJson('/app/api/branches/'.$branch->id)
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
            ->deleteJson('/app/api/branches/'.$branchId)
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

    public function test_branch_trip_sequence_start_can_be_set_on_create(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Sequence Co');

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $tenant->id,
                'branch_name' => 'Sequence Branch',
                'branch_contact_number' => '9876543212',
                'head_office' => true,
                'trip_sequence_start' => 10000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.trip_sequence_start', 10000)
            ->assertJsonPath('data.trip_sequence_start_locked', false);
    }

    public function test_branch_trip_sequence_start_cannot_change_after_trips_exist(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Locked Sequence Co');
        $branch = Branch::query()->create([
            'company_id' => $tenant->id,
            'branch_name' => 'Locked Branch',
            'branch_contact_number' => '9876543213',
            'head_office' => true,
            'trip_sequence_start' => 10000,
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
            'status' => 'CREATED',
        ]);

        $this->actingAs($superAdmin)
            ->putJson('/app/api/branches/'.$branch->id, [
                'trip_sequence_start' => 2000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['trip_sequence_start']);
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
        return Tenant::query()->create(array_merge([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'short_name' => str($name)->upper()->substr(0, 8)->toString(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ], $attributes));
    }
}
