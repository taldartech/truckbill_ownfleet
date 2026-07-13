<?php

namespace Tests\Feature\Api;

use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_can_create_and_update_state_and_city(): void
    {
        [$tenant, $user] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $stateResponse = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/states', [
                'name' => 'Test State',
                'code' => 'TS',
            ]);

        $stateResponse->assertCreated();
        $stateId = $stateResponse->json('data.id');

        $cityResponse = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/cities', [
                'state_id' => $stateId,
                'name' => 'Test City',
            ]);

        $cityResponse->assertCreated();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->putJson("/api/states/{$stateId}", [
                'name' => 'Test State Updated',
                'code' => 'TS',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Test State Updated');
    }

    public function test_states_and_cities_cannot_be_deleted(): void
    {
        [$tenant, $user] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $stateId = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/states', ['name' => 'No Delete State', 'code' => 'ND'])
            ->json('data.id');

        $cityId = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/cities', ['state_id' => $stateId, 'name' => 'No Delete City'])
            ->json('data.id');

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson("/api/cities/{$cityId}")
            ->assertMethodNotAllowed();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->deleteJson("/api/states/{$stateId}")
            ->assertMethodNotAllowed();
    }

    public function test_states_and_cities_index_are_paginated_with_fifteen_per_page(): void
    {
        [$tenant, $user] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $statesResponse = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/states?per_page=15');

        $statesResponse->assertOk();
        $statesResponse->assertJsonPath('meta.per_page', 15);
        $this->assertCount(15, $statesResponse->json('data'));
        $this->assertGreaterThan(1, $statesResponse->json('meta.last_page'));

        $citiesResponse = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/cities?per_page=15');

        $citiesResponse->assertOk();
        $citiesResponse->assertJsonPath('meta.per_page', 15);
        $this->assertCount(15, $citiesResponse->json('data'));
        $this->assertGreaterThan(1, $citiesResponse->json('meta.last_page'));
    }

    public function test_state_create_auto_creates_india_country_when_missing(): void
    {
        [$tenant, $user] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        \App\Country::query()->where('code', 'IN')->delete();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/states', [
                'name' => 'Auto Country State',
                'code' => 'AC',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Auto Country State');

        $this->assertDatabaseHas('countries', [
            'code' => 'IN',
            'name' => 'India',
        ]);
    }

    public function test_super_admin_can_load_location_lookups_without_branch_selected(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Platform Super Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $this->actingAs($superAdmin)
            ->getJson('/app/api/locations/countries')
            ->assertOk();

        $this->actingAs($superAdmin)
            ->getJson('/app/api/locations/states')
            ->assertOk();
    }

    public function test_moderator_can_view_but_not_create_location_master_data(): void
    {
        [$tenant, $user] = $this->createFixtures('moderator');
        Sanctum::actingAs($user);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->getJson('/api/states')
            ->assertOk();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/states', [
                'name' => 'Moderator State',
                'code' => 'MS',
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createFixtures(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Location Tenant',
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
