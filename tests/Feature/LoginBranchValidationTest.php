<?php

namespace Tests\Feature;

use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginBranchValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_without_branch_cannot_login_via_form(): void
    {
        $user = $this->createUserWithoutBranch('admin');

        $this->from('/app/login')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ])
            ->assertRedirect('/app/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_without_branch_cannot_login_via_json(): void
    {
        $user = $this->createUserWithoutBranch('admin');

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.email.0',
                'No branch is assigned to your account. Please contact your Super Admin.',
            );

        $this->assertGuest();
    }

    public function test_moderator_and_accountant_without_branch_cannot_login(): void
    {
        foreach (['moderator', 'accountant'] as $role) {
            $user = $this->createUserWithoutBranch($role);

            $this->postJson('/login', [
                'email' => $user->email,
                'password' => 'password',
            ])
                ->assertUnprocessable()
                ->assertJsonPath(
                    'errors.email.0',
                    'No branch is assigned to your account. Please contact your Super Admin.',
                );

            $this->assertGuest();
        }
    }

    public function test_admin_without_branch_cannot_login_via_api_token_endpoint(): void
    {
        $user = $this->createUserWithoutBranch('admin');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.email.0',
                'No branch is assigned to your account. Please contact your Super Admin.',
            );
    }

    public function test_accountant_without_branch_cannot_login_via_api_token_endpoint(): void
    {
        $user = $this->createUserWithoutBranch('accountant');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.email.0',
                'No branch is assigned to your account. Please contact your Super Admin.',
            );
    }

    public function test_admin_with_branch_can_login_via_api_token_endpoint(): void
    {
        $user = $this->createUserWithBranch('admin');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_admin_with_branch_can_login(): void
    {
        $user = $this->createUserWithBranch('admin');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/app/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_super_admin_without_branch_can_still_login(): void
    {
        $user = User::query()->create([
            'tenant_id' => null,
            'name' => 'Super Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/app/branches');

        $this->assertAuthenticatedAs($user);
    }

    private function createUserWithoutBranch(string $roleName): User
    {
        $tenant = Tenant::query()->create([
            'name' => 'Test Tenant',
            'slug' => fake()->unique()->slug(),
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

    private function createUserWithBranch(string $roleName): User
    {
        $user = $this->createUserWithoutBranch($roleName);

        return $this->assignBranchIfRequired($user, $user->tenant);
    }
}
