<?php

namespace Tests\Feature;

use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VueAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_vue_login_page(): void
    {
        $this->get('/app/login')
            ->assertOk()
            ->assertSee('id="app"', false);
    }

    public function test_guest_is_redirected_from_vue_dashboard(): void
    {
        $this->get('/app/dashboard')
            ->assertRedirect('/app/login');
    }

    public function test_authenticated_user_can_view_vue_dashboard(): void
    {
        $user = $this->createUser('admin');

        $this->actingAs($user)
            ->get('/app/dashboard')
            ->assertOk()
            ->assertSee('id="app"', false);
    }

    public function test_authenticated_user_can_view_vue_drivers_page(): void
    {
        $user = $this->createUser('admin');

        $this->actingAs($user)
            ->get('/app/drivers')
            ->assertOk()
            ->assertSee('id="app"', false);
    }

    public function test_authenticated_user_can_view_vue_challans_page(): void
    {
        $this->seedRolesAndPermissions();
        $user = $this->createUser('admin');

        $this->actingAs($user)
            ->get('/app/challans')
            ->assertOk()
            ->assertSee('id="app"', false);
    }

    public function test_api_me_returns_user_with_tenant_and_role(): void
    {
        $user = $this->createUser('admin');

        $this->actingAs($user)
            ->getJson('/app/user')
            ->assertOk()
            ->assertJsonPath('user.tenant_id', $user->tenant_id)
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.can_access_company_panel', true)
            ->assertJsonPath('user.tenant_name', 'Test Tenant');
    }

    public function test_guest_can_refresh_csrf_token(): void
    {
        $this->getJson('/app/csrf-token')
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_authenticated_user_can_refresh_csrf_token_and_create_driver(): void
    {
        $user = $this->createUser('admin');

        $this->actingAs($user)
            ->getJson('/app/csrf-token')
            ->assertOk()
            ->assertJsonStructure(['token']);

        $this->actingAs($user)
            ->postJson('/app/api/drivers', [
                'name' => 'CSRF Test Driver',
                'phone' => '9876543210',
                'joining_date' => '2024-01-15',
                'daily_wage' => 500,
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'CSRF Test Driver');
    }

    public function test_authenticated_user_can_load_dashboard_stats(): void
    {
        $user = $this->createUser('admin');

        $this->actingAs($user)
            ->getJson('/app/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure([
                'monthlyStats' => [
                    'total_bookings',
                    'active_trips',
                    'outstanding_amount',
                ],
                'invoicePaymentStatuses' => [
                    'pending',
                    'partial',
                    'paid',
                ],
            ]);
    }

    public function test_json_logout_redirects_to_vue_login(): void
    {
        $user = $this->createUser('admin');

        $this->actingAs($user)
            ->postJson('/logout')
            ->assertOk()
            ->assertJsonPath('redirect', '/app/login');

        $this->assertGuest();
    }

    public function test_json_login_returns_user_payload(): void
    {
        $user = $this->createUser('moderator');

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.role', 'moderator')
            ->assertJsonPath('user.tenant_id', $user->tenant_id)
            ->assertJsonPath('redirect', '/app/dashboard');
    }

    public function test_json_login_without_remember_still_authenticates_session(): void
    {
        $user = $this->createUser('admin');

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->getJson('/app/user')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_form_login_without_remember_can_load_vue_dashboard(): void
    {
        $user = $this->createUser('admin');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/app/dashboard');

        $this->get('/app/dashboard')
            ->assertOk()
            ->assertSee('id="app"', false)
            ->assertDontSee('Welcome back! Please sign in', false);
    }

    private function createUser(string $roleName): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        return User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor($roleName),
        ]);
    }
}
