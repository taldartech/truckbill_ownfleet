<?php

namespace Tests\Feature\Api;

use App\Support\SelectedCompanySession;
use App\Support\TenantContext;
use App\Support\TruckTypeDefaults;
use App\Tenant;
use App\TruckType;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TruckTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_can_list_and_create_truck_type(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        $this->actingAs($admin)
            ->getJson('/app/api/truck-types')
            ->assertOk()
            ->assertJsonFragment(['code' => 'TRUCK', 'name' => 'Truck']);

        $response = $this->actingAs($admin)
            ->postJson('/app/api/truck-types', [
                'name' => 'Dumper',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.code', 'DUMPER');
        $response->assertJsonPath('data.name', 'Dumper');

        $this->assertDatabaseHas('truck_types', [
            'tenant_id' => $tenant->id,
            'code' => 'DUMPER',
            'name' => 'Dumper',
        ]);
    }

    public function test_truck_type_cannot_be_deleted(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        TruckTypeDefaults::seedForTenant($tenant->id);
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        $type = TruckType::query()->where('code', 'TRUCK')->firstOrFail();

        $this->actingAs($admin)
            ->deleteJson("/app/api/truck-types/{$type->id}")
            ->assertForbidden();
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createTenantUser(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant '.fake()->company(),
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor($role) ?? UserRole::ADMIN,
        ]);

        return [$tenant, $user];
    }
}
