<?php

namespace Tests\Feature;

use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebCompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_ui_company_page_loads_for_super_admin(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)
            ->get('/ui/company')
            ->assertOk()
            ->assertSee('Company Management');
    }

    public function test_company_datatable_returns_json_for_ui_list(): void
    {
        $superAdmin = $this->createSuperAdmin();

        Tenant::query()->create([
            'owner_user_id' => $superAdmin->id,
            'name' => 'UI Transport',
            'slug' => 'ui-transport',
            'short_name' => 'UIT',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin)
            ->getJson('/ui/company', [
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->assertOk()
            ->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data'])
            ->assertJsonFragment(['gst_number' => '—']);
    }

    public function test_super_admin_can_enter_company_from_web_route(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $tenant = Tenant::query()->create([
            'owner_user_id' => $superAdmin->id,
            'name' => 'Enter Co',
            'slug' => 'enter-co',
            'short_name' => 'EC',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin)
            ->get('/company/'.$tenant->id.'/enter')
            ->assertRedirect();

        $this->assertSame($tenant->id, $superAdmin->fresh()->company_id);
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
}
