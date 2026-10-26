<?php

namespace Tests\Feature\Api;

use App\City;
use App\State;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrokerNameUniquenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_can_create_reference_with_duplicate_name_in_same_city(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');

        $state = State::query()->where('code', 'AR')->firstOrFail();
        $city = City::query()->where('state_id', $state->id)->where('name', 'Anjaw')->firstOrFail();

        $payload = [
            'name' => 'Duplicate Name Reference',
            'state_id' => $state->id,
            'city_id' => $city->id,
            'contact_number' => '9876543210',
        ];

        $this->actingAs($user)
            ->postJson('/app/api/brokers', $payload)
            ->assertCreated();

        $this->actingAs($user)
            ->postJson('/app/api/brokers', [
                ...$payload,
                'contact_number' => '9876543211',
            ])
            ->assertCreated();
    }

    public function test_same_reference_name_allowed_in_different_cities_within_tenant(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');

        $state = State::query()->where('code', 'AR')->firstOrFail();
        $cityA = City::query()->where('state_id', $state->id)->where('name', 'Anjaw')->firstOrFail();
        $cityB = City::query()->where('state_id', $state->id)->where('name', 'Changlang')->firstOrFail();

        $this->actingAs($user)
            ->postJson('/app/api/brokers', [
                'name' => 'Shared Name Reference',
                'state_id' => $state->id,
                'city_id' => $cityA->id,
                'contact_number' => '9876543210',
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->postJson('/app/api/brokers', [
                'name' => 'Shared Name Reference',
                'state_id' => $state->id,
                'city_id' => $cityB->id,
                'contact_number' => '9876543211',
            ])
            ->assertCreated();
    }

    public function test_admin_can_update_broker_details(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');

        $state = State::query()->where('code', 'AR')->firstOrFail();
        $city = City::query()->where('state_id', $state->id)->where('name', 'Anjaw')->firstOrFail();

        $createResponse = $this->actingAs($user)
            ->postJson('/app/api/brokers', [
                'name' => 'Original Broker Name',
                'state_id' => $state->id,
                'city_id' => $city->id,
                'contact_number' => '9876543210',
            ])
            ->assertCreated();

        $brokerId = $createResponse->json('data.id');

        $updateResponse = $this->actingAs($user)
            ->putJson("/app/api/brokers/{$brokerId}", [
                'name' => 'Updated Broker Name',
                'state_id' => $state->id,
                'city_id' => $city->id,
                'contact_number' => '9876543210', // keeping same contact number
                'address' => 'Updated Depot Road',
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Updated Broker Name')
            ->assertJsonPath('data.address', 'Updated Depot Road');

        $this->assertDatabaseHas('brokers', [
            'id' => $brokerId,
            'name' => 'Updated Broker Name',
            'address' => 'Updated Depot Road',
        ]);
    }

    public function test_broker_update_validates_city_belongs_to_state(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');

        $state = State::query()->where('code', 'AR')->firstOrFail();
        $otherState = State::query()->where('code', '!=', 'AR')->firstOrFail();
        $city = City::query()->where('state_id', $state->id)->firstOrFail();

        $createResponse = $this->actingAs($user)
            ->postJson('/app/api/brokers', [
                'name' => 'State Validation Broker',
                'state_id' => $state->id,
                'city_id' => $city->id,
                'contact_number' => '9876543299',
            ])
            ->assertCreated();

        $brokerId = $createResponse->json('data.id');

        $this->actingAs($user)
            ->putJson("/app/api/brokers/{$brokerId}", [
                'name' => 'State Validation Broker',
                'state_id' => $otherState->id,
                'city_id' => $city->id,
                'contact_number' => '9876543299',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['city_id']);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createTenantUser(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Broker Tenant',
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

        return [$tenant, $this->assignBranchIfRequired($user, $tenant)];
    }
}
