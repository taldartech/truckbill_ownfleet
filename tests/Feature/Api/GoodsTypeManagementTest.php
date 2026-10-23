<?php

namespace Tests\Feature\Api;

use App\GoodsType;
use App\Support\SelectedCompanySession;
use App\Support\TenantContext;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoodsTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_sees_empty_goods_types_until_created(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        $this->actingAs($admin)
            ->getJson('/app/api/goods-types')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_can_create_and_update_goods_type(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        $response = $this->actingAs($admin)
            ->postJson('/app/api/goods-types', [
                'name' => 'Auto Parts',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.code', 'auto_parts');
        $response->assertJsonPath('data.name', 'Auto Parts');

        $this->assertDatabaseHas('goods_types', [
            'tenant_id' => $tenant->id,
            'code' => 'auto_parts',
            'name' => 'Auto Parts',
            'is_active' => true,
        ]);

        $id = $response->json('data.id');

        $this->actingAs($admin)
            ->putJson("/app/api/goods-types/{$id}", [
                'name' => 'Auto Parts Updated',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Auto Parts Updated')
            ->assertJsonPath('data.code', 'auto_parts');
    }

    public function test_admin_can_deactivate_goods_type(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        $type = GoodsType::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Cement',
            'code' => 'cement',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/app/api/goods-types/{$type->id}", [
                'deleted_reason' => 'Decommissioned goods type',
            ])
            ->assertOk();

        $this->assertDatabaseHas('goods_types', [
            'id' => $type->id,
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->getJson('/app/api/goods-types')
            ->assertOk()
            ->assertJsonCount(0, 'data');
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
