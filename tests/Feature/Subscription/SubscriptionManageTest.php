<?php

namespace Tests\Feature\Subscription;

use App\Models\TenantSubscription;
use App\Support\TruckTypeDefaults;
use App\Support\TyreLayoutDefaults;
use App\Tenant;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionManageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_manage_page_counts_vehicles_without_tenant_context(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Usage Co');

        TyreLayoutDefaults::seedForTenant($tenant->id);
        TruckTypeDefaults::seedForTenant($tenant->id);

        foreach (['MH01AA1111', 'MH01BB2222', 'MH01CC3333'] as $registration) {
            Vehicle::query()->withoutGlobalScopes(['tenant', 'branch'])->create([
                ...$this->operationalAttributesForTenant($tenant),
                'registration_number' => $registration,
                'vehicle_type' => 'TRUCK',
                'make' => 'Tata',
                'model' => 'Ultra',
                'odometer_km' => 1000,
                'is_active' => true,
            ]);
        }

        $response = $this->actingAs($superAdmin)
            ->getJson('/app/api/subscription/manage?company_id='.$tenant->id)
            ->assertOk();

        $vehicleMetric = collect($response->json('usageMetrics'))
            ->firstWhere('label', 'Vehicles');

        $this->assertSame(3, $vehicleMetric['used']);
        $this->assertSame(50, $vehicleMetric['max']);
    }

    private function createSuperAdmin(): User
    {
        return User::query()->create([
            'tenant_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);
    }

    private function createOwnedTenant(User $owner, string $name): Tenant
    {
        $tenant = Tenant::query()->create([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => str($name)->slug().'-'.fake()->unique()->numerify('###'),
            'short_name' => Tenant::ensureUniqueShortName($name),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        TenantSubscription::query()->create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'included_branches' => 2,
            'users_per_branch' => 3,
            'included_trucks' => 50,
            'included_lr_credits' => 0,
            'addon_branches' => 0,
            'addon_users' => 0,
            'addon_trucks' => 0,
            'addon_lr_credits' => 0,
            'lr_credits_remaining' => 0,
        ]);

        return $tenant;
    }
}
