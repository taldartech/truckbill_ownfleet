<?php

namespace Tests\Feature;

use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_dashboard(): void
    {
        $this->get('/ui/dashboard/roles/admin')
            ->assertRedirect('/app/login');
    }

    public function test_user_can_login_and_is_redirected_to_role_dashboard(): void
    {
        $user = $this->createUser('admin');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/app/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_logout(): void
    {
        $user = $this->createUser('admin');

        $this->actingAs($user)
            ->get('/logout')
            ->assertRedirect('/app/login?logged_out=1');

        $this->assertGuest();
    }

    public function test_invalid_login_shows_error(): void
    {
        $user = $this->createUser('admin');

        $this->from('/app/login')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/app/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
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
