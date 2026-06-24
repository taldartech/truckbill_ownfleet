<?php

namespace Tests\Feature\Api;

use App\Driver;
use App\Support\SelectedCompanySession;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_super_admin_can_create_and_enter_owned_company(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'ABC Transport',
                'gst_number' => '27AAAAA0000A1Z5',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'ABC Transport')
            ->assertJsonPath('data.tenant_id', fn ($id) => $id !== null)
            ->assertJsonPath('data.company_id', fn ($id) => $id !== null);

        $tenantId = Tenant::query()->where('owner_user_id', $superAdmin->id)->value('id');

        $this->assertNotNull($tenantId);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/companies/'.$tenantId.'/enter')
            ->assertOk()
            ->assertJsonPath('selected_company_id', $tenantId)
            ->assertJsonPath('selected_tenant_id', $tenantId);

        $this->assertSame($tenantId, SelectedCompanySession::id());
        $this->assertSame($tenantId, $superAdmin->fresh()->company_id);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/drivers', [
                'name' => 'Scoped Driver',
                'phone' => '9888888881',
                'license_number' => 'DL-SCOPE',
                'joining_date' => '2024-01-15',
                'daily_wage' => 500,
                'is_active' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('drivers', [
            'tenant_id' => $tenantId,
            'name' => 'Scoped Driver',
        ]);
    }

    public function test_super_admin_dashboard_stats_after_enter_do_not_require_company(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Stats Co');

        $this->actingAs($superAdmin)
            ->postJson('/app/api/companies/'.$tenant->id.'/enter')
            ->assertOk();

        $this->actingAs($superAdmin)
            ->getJson('/app/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('requires_company', null)
            ->assertJsonStructure([
                'fleet' => [
                    'vehicles_count',
                    'drivers_count',
                    'present_today_count',
                ],
            ]);
    }

    public function test_super_admin_without_company_context_cannot_create_driver(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)
            ->postJson('/app/api/drivers', [
                'name' => 'Blocked Driver',
                'phone' => '9888888882',
                'license_number' => 'DL-BLOCK',
                'joining_date' => '2024-01-15',
                'daily_wage' => 500,
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_super_admin_sees_only_owned_companies(): void
    {
        $superAdminA = $this->createSuperAdmin();
        $superAdminB = $this->createSuperAdmin();

        Tenant::query()->create([
            'owner_user_id' => $superAdminA->id,
            'name' => 'Company A',
            'slug' => 'company-a',
            'short_name' => 'CA',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        Tenant::query()->create([
            'owner_user_id' => $superAdminB->id,
            'name' => 'Company B',
            'slug' => 'company-b',
            'short_name' => 'CB',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdminA)
            ->getJson('/app/api/companies');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertSame(['Company A'], $names);
    }

    public function test_super_admin_exit_clears_company_context(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Exit Co');

        SelectedCompanySession::set($tenant->id);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/companies/exit')
            ->assertOk();

        $this->assertNull(SelectedCompanySession::id());
        $this->assertNull($superAdmin->fresh()->company_id);
    }

    public function test_master_admin_can_list_all_companies_and_block(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Block Me');
        $masterAdmin = $this->createMasterAdmin();

        $this->actingAs($masterAdmin)
            ->getJson('/app/api/companies')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Block Me']);

        $this->actingAs($masterAdmin)
            ->putJson('/app/api/companies/'.$tenant->id, ['is_blocked' => true])
            ->assertOk()
            ->assertJsonPath('data.is_blocked', true);
    }

    public function test_master_admin_can_set_company_payroll_pay_days_mode(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Pay Days Co');
        $masterAdmin = $this->createMasterAdmin();

        $this->actingAs($masterAdmin)
            ->putJson('/app/api/companies/'.$tenant->id, [
                'payroll_pay_days_mode' => 'fixed_30',
            ])
            ->assertOk()
            ->assertJsonPath('data.payroll_pay_days_mode', 'fixed_30');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'payroll_pay_days_mode' => 'fixed_30',
        ]);
    }

    public function test_tenant_id_and_company_id_alias_match_on_driver(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();

        $driver = Driver::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Alias Driver',
            'phone' => '9000000001',
            'license_number' => 'DL-ALIAS',
            'daily_wage' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson('/app/api/drivers/'.$driver->id)
            ->assertOk()
            ->assertJsonPath('data.tenant_id', $tenant->id);

        $this->assertSame($admin->company_id, $tenant->id);
    }

    public function test_cannot_create_company_without_gst_pan_or_contact_number(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'No Identity Co',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['gst_number', 'pan_number', 'contact_number']);
    }

    public function test_can_create_company_with_only_pan_number(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'PAN Only Co',
                'pan_number' => 'ABCDE1234F',
            ])
            ->assertCreated()
            ->assertJsonPath('data.pan_number', 'ABCDE1234F');
    }

    public function test_cannot_update_company_to_remove_all_identity_fields(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Identity Co', [
            'gst_number' => '27AAAAA0000A1Z5',
        ]);

        $response = $this->actingAs($superAdmin)
            ->putJson('/app/api/companies/'.$tenant->id, [
                'gst_number' => '',
                'pan_number' => '',
                'contact_number' => '',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['gst_number', 'pan_number', 'contact_number']);
    }

    public function test_cannot_create_company_with_duplicate_gst_or_pan(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'First Transport',
                'gst_number' => '27AAAAA0000A1Z5',
                'pan_number' => 'ABCDE1234F',
            ])
            ->assertCreated();

        $duplicate = $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'Second Transport',
                'gst_number' => '27aaaaa0000a1z5',
                'pan_number' => 'abcde1234f',
            ]);

        $duplicate->assertUnprocessable();
        $duplicate->assertJsonValidationErrors(['gst_number', 'pan_number']);
    }

    public function test_company_can_keep_its_own_gst_and_pan_on_update(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Update Co');

        $tenant->update([
            'gst_number' => '27AAAAA0000A1Z5',
            'pan_number' => 'ABCDE1234F',
        ]);

        $this->actingAs($superAdmin)
            ->putJson('/app/api/companies/'.$tenant->id, [
                'name' => 'Updated Co',
                'gst_number' => '27AAAAA0000A1Z5',
                'pan_number' => 'ABCDE1234F',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Co');
    }

    public function test_cannot_update_company_to_use_another_companys_gst_or_pan(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->createOwnedTenant($superAdmin, 'Existing Co', [
            'gst_number' => '27AAAAA0000A1Z5',
            'pan_number' => 'ABCDE1234F',
        ]);

        $otherTenant = $this->createOwnedTenant($superAdmin, 'Other Co');

        $response = $this->actingAs($superAdmin)
            ->putJson('/app/api/companies/'.$otherTenant->id, [
                'gst_number' => '27AAAAA0000A1Z5',
                'pan_number' => 'ABCDE1234F',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['gst_number', 'pan_number']);
    }

    public function test_cannot_create_company_with_invalid_or_duplicate_contact_number(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'Contact Co',
                'contact_number' => '9876543210',
            ])
            ->assertCreated();

        $invalidFormat = $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'Bad Contact Co',
                'contact_number' => '12345',
            ]);

        $invalidFormat->assertUnprocessable();
        $invalidFormat->assertJsonValidationErrors(['contact_number']);

        $duplicate = $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'Duplicate Contact Co',
                'contact_number' => '98765 43210',
            ]);

        $duplicate->assertUnprocessable();
        $duplicate->assertJsonValidationErrors(['contact_number']);
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

    private function createMasterAdmin(): User
    {
        return User::query()->create([
            'tenant_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::MASTER_ADMIN,
        ]);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createTenantAdmin(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Staff Tenant',
            'slug' => 'staff-tenant',
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

        return [$tenant, $admin];
    }

    private function createOwnedTenant(User $owner, string $name, array $attributes = []): Tenant
    {
        return Tenant::query()->create([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => str()->slug($name).'-'.fake()->unique()->numerify('###'),
            'short_name' => Tenant::ensureUniqueShortName($name),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
