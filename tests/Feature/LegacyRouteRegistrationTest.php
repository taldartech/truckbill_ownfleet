<?php

namespace Tests\Feature;

use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyRouteRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_renders_without_missing_route_errors(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@test.local',
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);

        $this->actingAs($user)
            ->get('/ui/dashboard/roles/admin')
            ->assertOk()
            ->assertSee('Total Bookings', false)
            ->assertDontSee('Dashboard content is being prepared for your role', false);
    }
}
