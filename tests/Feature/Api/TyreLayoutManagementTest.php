<?php

namespace Tests\Feature\Api;

use App\Support\TyreLayoutDefaults;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TyreLayoutManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_can_list_and_create_tyre_layout(): void
    {
        [$tenant, $user] = $this->createFixtures();
        Sanctum::actingAs($user);

        $list = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/tyre-layouts?per_page=500');

        $list->assertOk();
        $list->assertJsonFragment(['code' => 'TYRES_10']);

        $create = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/tyre-layouts', [
                'name' => 'Custom 8 tyre',
                'layout_type' => 'standard',
                'steer_axles' => 1,
                'dual_axles' => [1],
                'single_axles' => [],
                'spare_count' => 2,
            ]);

        $create->assertCreated();
        $create->assertJsonPath('data.position_count', 8);
        $create->assertJsonPath('data.code', 'CUSTOM_8_TYRE');
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createFixtures(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Layout Test Co',
            'slug' => 'layout-test-co',
            'short_name' => 'LTC',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
            'is_blocked' => false,
        ]);

        TyreLayoutDefaults::seedForTenant($tenant->id);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor(UserRole::ADMIN) ?? UserRole::ADMIN,
        ]);

        return [$tenant, $user];
    }
}
