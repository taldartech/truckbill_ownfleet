<?php

namespace Tests\Feature\Api;

use App\Branch;
use App\City;
use App\State;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerAndBrokerTenantSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_customer_created_in_one_branch_is_visible_from_another_branch(): void
    {
        [$tenant, $user, $branchA, $branchB] = $this->createTenantWithTwoBranches();
        Sanctum::actingAs($user);

        $state = State::query()->where('code', 'AR')->firstOrFail();
        $city = City::query()->where('state_id', $state->id)->where('name', 'Anjaw')->firstOrFail();

        $this->withHeaders([
            'X-Tenant-Id' => $tenant->id,
            'X-Branch-Id' => (string) $branchA->id,
        ])->postJson('/api/customers', [
            'name' => 'Shared Customer',
            'state_id' => $state->id,
            'city_id' => $city->id,
            'contact_number' => '9876543210',
        ])->assertCreated();

        $this->withHeaders([
            'X-Tenant-Id' => $tenant->id,
            'X-Branch-Id' => (string) $branchB->id,
        ])->getJson('/api/customers?per_page=500')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Shared Customer']);
    }

    public function test_broker_created_in_one_branch_is_visible_from_another_branch(): void
    {
        [$tenant, $user, $branchA, $branchB] = $this->createTenantWithTwoBranches();
        Sanctum::actingAs($user);

        $state = State::query()->where('code', 'AR')->firstOrFail();
        $city = City::query()->where('state_id', $state->id)->where('name', 'Anjaw')->firstOrFail();

        $this->withHeaders([
            'X-Tenant-Id' => $tenant->id,
            'X-Branch-Id' => (string) $branchA->id,
        ])->postJson('/api/brokers', [
            'name' => 'Shared Reference',
            'state_id' => $state->id,
            'city_id' => $city->id,
            'contact_number' => '9876543210',
        ])->assertCreated();

        $this->withHeaders([
            'X-Tenant-Id' => $tenant->id,
            'X-Branch-Id' => (string) $branchB->id,
        ])->getJson('/api/brokers?per_page=500')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Shared Reference']);
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Branch, 3: Branch}
     */
    private function createTenantWithTwoBranches(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Sharing Tenant',
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $branchA = $this->createBranchForTenant($tenant, [
            'branch_name' => 'Branch A',
            'head_office' => true,
        ]);

        $branchB = $this->createBranchForTenant($tenant, [
            'branch_name' => 'Branch B',
            'head_office' => false,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor(UserRole::ADMIN) ?? UserRole::ADMIN,
        ]);

        return [$tenant, $this->assignBranchIfRequired($user, $tenant), $branchA, $branchB];
    }
}
