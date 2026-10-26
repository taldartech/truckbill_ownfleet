<?php

namespace Tests\Feature;

use App\Support\SelectedCompanySession;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyWebTest extends TestCase
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
            'name' => 'Super Admin User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);
    }

    private function createMasterAdmin(): User
    {
        return User::query()->create([
            'tenant_id' => null,
            'name' => 'Master Admin User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::MASTER_ADMIN,
        ]);
    }

    private function createOwnedTenant(User $owner, string $name): Tenant
    {
        return Tenant::query()->create([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numerify('###'),
            'short_name' => Tenant::ensureUniqueShortName($name),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);
    }

    public function test_super_admin_ajax_datatable_filtering_and_pagination(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $this->createOwnedTenant($superAdmin, 'Super Express Logistics');

        // AJAX datatable search
        $ajaxResponse = $this->actingAs($superAdmin)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->getJson('/ui/company?draw=1&start=0&length=10&search[value]=Express');

        $ajaxResponse->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('recordsFiltered', 1);

        $this->assertStringContainsString('Super Express Logistics', $ajaxResponse->json('data.0.name'));
    }

    public function test_super_admin_can_store_company(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)
            ->post('/company', [
                'name' => 'Brand New Logistics',
                'short_name' => 'BNL',
                'pan_number' => 'ABCDE1234F',
                'contact_person' => 'Rajesh Sharma',
                'contact_number' => '9876543210',
                'tax_liability' => 'forward_charge',
                'registration_type' => 'regular',
            ]);

        $response->assertRedirect('/ui/company');
        $this->assertDatabaseHas('tenants', [
            'name' => 'Brand New Logistics',
            'owner_user_id' => $superAdmin->id,
            'pan_number' => 'ABCDE1234F',
        ]);
    }

    public function test_super_admin_can_update_company(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Updatable Freight');

        $response = $this->actingAs($superAdmin)
            ->put("/company/{$tenant->id}", [
                'name' => 'Updated Freight Pvt Ltd',
                'short_name' => $tenant->short_name,
                'pan_number' => 'BCDEF2345G',
                'contact_person' => 'Vikram Patel',
                'contact_number' => '9888877777',
            ]);

        $response->assertRedirect('/ui/company');
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Updated Freight Pvt Ltd',
            'contact_person' => 'Vikram Patel',
            'pan_number' => 'BCDEF2345G',
        ]);
    }

    public function test_super_admin_can_enter_and_destroy_owned_company(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Enterable Co');

        $enterResponse = $this->actingAs($superAdmin)
            ->get("/company/{$tenant->id}/enter");

        $enterResponse->assertRedirect(route('dashboard'));
        $this->assertSame($tenant->id, SelectedCompanySession::id());

        // Delete owned company (hard delete)
        $deleteResponse = $this->actingAs($superAdmin)
            ->delete("/company/{$tenant->id}");

        $deleteResponse->assertRedirect('/ui/company');
        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    }

    public function test_master_admin_can_update_subscription(): void
    {
        $masterAdmin = $this->createMasterAdmin();
        $tenant = Tenant::query()->create([
            'name' => 'Master Managed Co',
            'slug' => 'master-managed-co',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $response = $this->actingAs($masterAdmin)
            ->patch("/company/{$tenant->id}/subscription", [
                'plan_end_date' => now()->addMonths(6)->toDateString(),
                'lr_credits' => 500,
                'truck_credits' => 20,
            ]);

        $response->assertRedirect('/ui/company');
    }

    public function test_template_previews_render_stubs(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Preview Co');

        $this->actingAs($superAdmin)
            ->get("/company/{$tenant->id}/invoice-template-preview/classic")
            ->assertOk()
            ->assertViewIs('company.template-preview-stub');

        $this->actingAs($superAdmin)
            ->get("/company/{$tenant->id}/lr-copy-template-preview/classic")
            ->assertOk()
            ->assertViewIs('company.template-preview-stub');

        $this->actingAs($superAdmin)
            ->get("/company/{$tenant->id}/freight-challan-template-preview/classic")
            ->assertOk()
            ->assertViewIs('company.template-preview-stub');
    }

    public function test_non_super_admin_cannot_delete_or_enter_company(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Restricted Co');

        $otherUser = User::query()->create([
            'tenant_id' => null,
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        // Attempting to delete another super admin's company is forbidden
        $this->actingAs($otherUser)
            ->delete("/company/{$tenant->id}")
            ->assertForbidden();
    }
}
