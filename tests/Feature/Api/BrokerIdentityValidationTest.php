<?php

namespace Tests\Feature\Api;

use App\City;
use App\State;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrokerIdentityValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_cannot_create_reference_without_contact_number(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        [$stateId, $cityId] = $this->locationIds();

        $this->actingAs($user)
            ->postJson('/app/api/brokers', [
                'name' => 'Reference Without Contact',
                'state_id' => $stateId,
                'city_id' => $cityId,
                'contact_number' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_number']);
    }

    public function test_cannot_create_reference_with_invalid_contact_number(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        [$stateId, $cityId] = $this->locationIds();

        $this->actingAs($user)
            ->postJson('/app/api/brokers', [
                'name' => 'Reference Invalid Contact',
                'state_id' => $stateId,
                'city_id' => $cityId,
                'contact_number' => '12345',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_number']);
    }

    public function test_cannot_create_reference_with_invalid_pan_number(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        [$stateId, $cityId] = $this->locationIds();

        $this->actingAs($user)
            ->postJson('/app/api/brokers', [
                'name' => 'Reference Invalid PAN',
                'state_id' => $stateId,
                'city_id' => $cityId,
                'contact_number' => '9876543210',
                'pan_number' => 'INVALID',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pan_number']);
    }

    public function test_cannot_create_reference_with_duplicate_identity_in_same_tenant(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        [$stateId, $cityId] = $this->locationIds();

        $payload = [
            'name' => 'Identity Reference',
            'state_id' => $stateId,
            'city_id' => $cityId,
            'gst_number' => '22AAAAA0000A1Z5',
            'pan_number' => 'ABCDE1234F',
            'contact_number' => '9876543210',
        ];

        $this->actingAs($user)
            ->postJson('/app/api/brokers', $payload)
            ->assertCreated();

        $duplicate = $this->actingAs($user)
            ->postJson('/app/api/brokers', [
                ...$payload,
                'name' => 'Duplicate Identity Reference',
            ]);

        $duplicate->assertUnprocessable();
        $duplicate->assertJsonValidationErrors(['gst_number', 'pan_number', 'contact_number']);
    }

    public function test_same_reference_identity_allowed_in_different_tenants(): void
    {
        [$tenantA, $userA] = $this->createTenantUser('admin');
        [$tenantB, $userB] = $this->createTenantUser('admin');
        [$stateId, $cityId] = $this->locationIds();

        $payload = [
            'name' => 'Shared Identity Reference',
            'state_id' => $stateId,
            'city_id' => $cityId,
            'gst_number' => '22AAAAA0000A1Z5',
            'pan_number' => 'ABCDE1234F',
            'contact_number' => '9876543210',
        ];

        $this->actingAs($userA)
            ->postJson('/app/api/brokers', $payload)
            ->assertCreated();

        $this->actingAs($userB)
            ->postJson('/app/api/brokers', $payload)
            ->assertCreated();
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

    /**
     * @return array{0: int, 1: int}
     */
    private function locationIds(): array
    {
        $state = State::query()->where('code', 'AR')->firstOrFail();
        $city = City::query()
            ->where('state_id', $state->id)
            ->where('name', 'Anjaw')
            ->firstOrFail();

        return [$state->id, $city->id];
    }
}
