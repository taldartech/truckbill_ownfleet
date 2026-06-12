<?php

namespace Tests\Feature\Api;

use App\City;
use App\State;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerIdentityValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_customer_identity_fields_allow_null_values(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        Sanctum::actingAs($user);

        [$stateId, $cityId] = $this->locationIds();

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/customers', [
                'name' => 'Null Identity Customer',
                'state_id' => $stateId,
                'city_id' => $cityId,
                'gst_number' => null,
                'pan_number' => null,
                'contact_number' => null,
            ])
            ->assertCreated();
    }

    public function test_cannot_create_customer_with_duplicate_identity_in_same_tenant(): void
    {
        [$tenant, $user] = $this->createTenantUser('admin');
        Sanctum::actingAs($user);

        [$stateId, $cityId] = $this->locationIds();

        $payload = [
            'name' => 'Identity Customer',
            'state_id' => $stateId,
            'city_id' => $cityId,
            'gst_number' => '22AAAAA0000A1Z5',
            'pan_number' => 'ABCDE1234F',
            'contact_number' => '9876543210',
        ];

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/customers', $payload)
            ->assertCreated();

        $duplicate = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->postJson('/api/customers', [
                ...$payload,
                'name' => 'Duplicate Identity Customer',
            ]);

        $duplicate->assertUnprocessable();
        $duplicate->assertJsonValidationErrors(['gst_number', 'pan_number', 'contact_number']);
    }

    public function test_same_customer_identity_allowed_in_different_tenants(): void
    {
        [$tenantA, $userA] = $this->createTenantUser('admin');
        [$tenantB, $userB] = $this->createTenantUser('admin');
        Sanctum::actingAs($userA);

        [$stateId, $cityId] = $this->locationIds();

        $payload = [
            'name' => 'Shared Identity Customer',
            'state_id' => $stateId,
            'city_id' => $cityId,
            'gst_number' => '22AAAAA0000A1Z5',
            'pan_number' => 'ABCDE1234F',
            'contact_number' => '9876543210',
        ];

        $this->withHeader('X-Tenant-Id', $tenantA->id)
            ->postJson('/api/customers', $payload)
            ->assertCreated();

        Sanctum::actingAs($userB);

        $this->withHeader('X-Tenant-Id', $tenantB->id)
            ->postJson('/api/customers', $payload)
            ->assertCreated();
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createTenantUser(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Customer Tenant',
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
