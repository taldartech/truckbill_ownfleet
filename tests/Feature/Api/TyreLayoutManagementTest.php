<?php

namespace Tests\Feature\Api;

use App\Support\TyreLayoutDefaults;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->provisionActiveSubscription($tenant);
        $user = $this->assignBranchIfRequired($user, $tenant);

        $list = $this->actingAs($user)
            ->getJson('/app/api/tyre-layouts?per_page=500');

        $list->assertOk();
        $list->assertJsonFragment(['code' => 'TYRES_10']);

        $create = $this->actingAs($user)
            ->postJson('/app/api/tyre-layouts', [
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

    public function test_user_can_create_first_tyre_layout_when_no_data_exists(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Zero Layout Tenant',
            'slug' => 'zero-layout-tenant',
            'short_name' => 'ZLT',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $this->provisionActiveSubscription($tenant);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Empty Layout Admin',
            'email' => 'admin@zero-layout.com',
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);
        $user = $this->assignBranchIfRequired($user, $tenant);

        $this->assertSame(0, \App\TyreLayout::where('tenant_id', $tenant->id)->count());

        // Creating directly without viewing auto-creates custom layout
        $create = $this->actingAs($user)
            ->postJson('/app/api/tyre-layouts', [
                'name' => 'Brand New 10 Tyre',
                'layout_type' => 'standard',
                'steer_axles' => 1,
                'dual_axles' => [1, 2],
                'single_axles' => [],
                'spare_count' => 0,
            ]);

        $create->assertCreated();
        $create->assertJsonPath('data.name', 'Brand New 10 Tyre');
        $create->assertJsonPath('data.position_count', 10);
    }

    public function test_index_auto_seeds_standard_presets_when_tenant_has_no_layouts(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Auto Seed Tenant',
            'slug' => 'auto-seed-tenant',
            'short_name' => 'AST',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $this->provisionActiveSubscription($tenant);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Auto Seed Admin',
            'email' => 'admin@auto-seed.com',
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);
        $user = $this->assignBranchIfRequired($user, $tenant);

        $this->assertSame(0, \App\TyreLayout::where('tenant_id', $tenant->id)->count());

        $list = $this->actingAs($user)
            ->getJson('/app/api/tyre-layouts');

        $list->assertOk();
        $list->assertJsonFragment(['code' => 'TYRES_10']);
        $this->assertSame(8, \App\TyreLayout::where('tenant_id', $tenant->id)->count());
    }

    public function test_user_can_explicitly_seed_standard_presets_endpoint(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Explicit Seed Tenant',
            'slug' => 'explicit-seed-tenant',
            'short_name' => 'EST',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $this->provisionActiveSubscription($tenant);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Explicit Seed Admin',
            'email' => 'admin@explicit-seed.com',
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);
        $user = $this->assignBranchIfRequired($user, $tenant);

        $this->assertSame(0, \App\TyreLayout::where('tenant_id', $tenant->id)->count());

        $seed = $this->actingAs($user)
            ->postJson('/app/api/tyre-layouts/seed-defaults');

        $seed->assertOk();
        $seed->assertJsonFragment(['code' => 'TYRES_10']);
        $this->assertSame(8, \App\TyreLayout::where('tenant_id', $tenant->id)->count());
    }

    public function test_super_admin_can_create_tyre_layout_when_entered_into_company(): void
    {
        $superAdmin = User::query()->create([
            'tenant_id' => null,
            'name' => 'Super Admin',
            'email' => 'super@admin.com',
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $tenant = Tenant::query()->create([
            'owner_user_id' => $superAdmin->id,
            'name' => 'Super Admin Co',
            'slug' => 'super-admin-co',
            'short_name' => 'SAC',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);
        $this->provisionActiveSubscription($tenant);
        $branch = $this->createBranchForTenant($tenant);

        // Before entering company: effectiveTenantId is null
        $this->actingAs($superAdmin);
        $responseBeforeEnter = $this->postJson('/app/api/tyre-layouts', [
            'name' => 'Cannot Add Yet',
            'layout_type' => 'standard',
            'steer_axles' => 1,
            'dual_axles' => [1],
            'single_axles' => [],
            'spare_count' => 0,
        ]);
        $responseBeforeEnter->assertForbidden();

        // After entering company
        $this->enterBranchForSuperAdmin($superAdmin, $branch);

        $responseAfterEnter = $this->actingAs($superAdmin)
            ->postJson('/app/api/tyre-layouts', [
                'name' => 'Super Admin 10 Tyre',
                'layout_type' => 'standard',
                'steer_axles' => 1,
                'dual_axles' => [1, 2],
                'single_axles' => [],
                'spare_count' => 0,
            ]);

        $responseAfterEnter->assertCreated();
    }

    public function test_vehicle_creation_flow_auto_seeds_tyre_layouts_when_tenant_had_none(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Zero Layout Truck Tenant',
            'slug' => 'zero-layout-truck-tenant',
            'short_name' => 'ZLTT',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $this->provisionActiveSubscription($tenant);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Truck Manager',
            'email' => 'trucks@zero-layout.com',
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);
        $user = $this->assignBranchIfRequired($user, $tenant);

        // Tenant starts with 0 tyre layouts
        $this->assertSame(0, \App\TyreLayout::where('tenant_id', $tenant->id)->count());

        // Modal fetches layouts via listAllTyreLayouts (/app/api/tyre-layouts?per_page=500&active_only=1)
        $modalFetch = $this->actingAs($user)
            ->getJson('/app/api/tyre-layouts?per_page=500&active_only=1');

        $modalFetch->assertOk();
        $modalFetch->assertJsonFragment(['code' => 'TYRES_10']);

        // Now tenant has auto-seeded 8 presets
        $this->assertSame(8, \App\TyreLayout::where('tenant_id', $tenant->id)->count());

        // Truck types also seed on truck types index
        $this->actingAs($user)->getJson('/app/api/truck-types');

        // Creating vehicle now succeeds with TYRES_10
        $truckCreate = $this->actingAs($user)
            ->postJson('/app/api/vehicles', [
                'registration_number' => 'MH12AB9999',
                'vehicle_type' => 'TRUCK',
                'tyre_layout' => 'TYRES_10',
            ]);

        $truckCreate->assertCreated();
        $truckCreate->assertJsonPath('data.registration_number', 'MH12AB9999');
        $truckCreate->assertJsonPath('data.tyre_layout', 'TYRES_10');
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
