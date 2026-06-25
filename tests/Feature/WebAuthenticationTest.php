<?php

namespace Tests\Feature;

use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_login_invalidates_other_sessions_for_same_user(): void
    {
        $user = $this->createUser('admin');

        DB::table('sessions')->insert([
            [
                'id' => 'old-device-session',
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Other device',
                'payload' => base64_encode(''),
                'last_activity' => time(),
            ],
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/app/dashboard');

        $this->assertDatabaseMissing('sessions', [
            'id' => 'old-device-session',
        ]);
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
            ->assertSessionHasErrors([
                'email' => 'Incorrect password.',
            ]);

        $this->assertGuest();
    }

    public function test_login_with_unknown_email_shows_email_does_not_exist_error(): void
    {
        $this->from('/app/login')
            ->post('/login', [
                'email' => 'unknown@example.com',
                'password' => 'password',
            ])
            ->assertRedirect('/app/login')
            ->assertSessionHasErrors([
                'email' => 'Email does not exist.',
            ]);

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

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor($roleName),
        ]);

        return $this->assignBranchIfRequired($user, $tenant);
    }
}
