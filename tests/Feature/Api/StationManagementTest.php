<?php

namespace Tests\Feature\Api;

use App\Branch;
use App\City;
use App\Models\TenantSubscription;
use App\Station;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_stations_are_shared_across_branches_within_company(): void
    {
        [$tenant, $user, $firstBranchId, $secondBranch] = $this->createCompanyWithTwoBranches('admin');

        $city = City::query()
            ->whereHas('state', fn ($query) => $query->where('code', 'MH'))
            ->where('name', 'Mumbai')
            ->firstOrFail();

        $station = Station::query()->create([
            'tenant_id' => $tenant->id,
            'country_id' => $city->state->country_id,
            'state_id' => $city->state_id,
            'city_id' => $city->id,
            'name' => 'Shared Depot',
        ]);

        $this->actingAs($user);
        $this->enterBranchForSuperAdmin($user, Branch::query()->findOrFail($firstBranchId));

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $firstBranchId)
            ->getJson('/api/stations?per_page=500')
            ->assertOk()
            ->assertJsonFragment(['id' => $station->id, 'name' => 'Shared Depot']);

        $this->enterBranchForSuperAdmin($user, $secondBranch);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $secondBranch->id)
            ->getJson('/api/stations?per_page=500')
            ->assertOk()
            ->assertJsonFragment(['id' => $station->id, 'name' => 'Shared Depot']);
    }

    public function test_station_created_in_one_branch_is_visible_in_other_branch(): void
    {
        [$tenant, $user, $firstBranchId, $secondBranch] = $this->createCompanyWithTwoBranches('admin');

        $city = City::query()
            ->whereHas('state', fn ($query) => $query->where('code', 'MH'))
            ->where('name', 'Pune')
            ->firstOrFail();

        $this->actingAs($user);
        $this->enterBranchForSuperAdmin($user, Branch::query()->findOrFail($firstBranchId));

        $create = $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $firstBranchId)
            ->postJson('/api/stations', [
                'name' => 'Pune Hub',
                'state_id' => $city->state_id,
                'city_id' => $city->id,
            ])
            ->assertCreated();

        $stationId = $create->json('data.id');

        $this->assertNull(Station::query()->find($stationId)?->branch_id);

        $this->enterBranchForSuperAdmin($user, $secondBranch);

        $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $secondBranch->id)
            ->getJson('/api/stations/'.$stationId)
            ->assertOk()
            ->assertJsonPath('data.name', 'Pune Hub');
    }

    /**
     * @return array{0: Tenant, 1: User, 2: int, 3: Branch}
     */
    private function createCompanyWithTwoBranches(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Station Tenant',
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'included_branches' => 10,
            'users_per_branch' => 3,
            'included_trucks' => 50,
            'included_lr_credits' => 0,
            'addon_branches' => 0,
            'addon_users' => 0,
            'addon_trucks' => 0,
            'addon_lr_credits' => 0,
            'lr_credits_remaining' => 0,
        ]);

        $operationalAttributes = $this->operationalAttributesForTenant($tenant);
        $firstBranchId = $operationalAttributes['branch_id'];

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $firstBranchId,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor($role) ?? UserRole::ADMIN,
        ]);

        $secondBranch = $this->createBranchForTenant($tenant, [
            'branch_name' => 'Second Branch',
            'head_office' => false,
        ]);

        return [$tenant, $user, $firstBranchId, $secondBranch];
    }
}
