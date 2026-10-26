<?php

namespace Tests\Feature\Api;

use App\OnboardingProvisioningLog;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingApiTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNING_SECRET = 'test-onboarding-secret-key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        config(['onboarding.signing_secret' => self::SIGNING_SECRET]);
    }

    private function signedRequest(string $method, string $uri, array $payload = [])
    {
        $timestamp = (string) time();

        if ($method === 'GET') {
            $signature = hash_hmac('sha256', $timestamp . '.', self::SIGNING_SECRET);

            return $this->withHeaders([
                'Accept' => 'application/json',
                'X-Timestamp' => $timestamp,
                'X-Signature' => $signature,
            ])->get($uri);
        }

        $content = json_encode($payload);
        $signature = hash_hmac('sha256', $timestamp . '.' . $content, self::SIGNING_SECRET);

        return $this->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ])->json($method, $uri, $payload);
    }

    public function test_create_user_provisions_super_admin_successfully(): void
    {
        $payload = [
            'idempotency_key' => 'idemp-001-xyz',
            'name' => 'Owner Admin',
            'email' => 'owner@truckbill.test',
            'mobile' => '9876543210',
            'password' => 'secretPassword123!',
            'role' => 'super_admin',
            'email_verified_at' => now()->toISOString(),
            'policy_accepted_at' => now()->toISOString(),
        ];

        $response = $this->signedRequest('POST', '/api/onboarding/create-user', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'created',
                'email' => 'owner@truckbill.test',
                'role' => 'super_admin',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'owner@truckbill.test',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $this->assertDatabaseHas('onboarding_provisioning_logs', [
            'idempotency_key' => 'idemp-001-xyz',
            'action' => 'create_user',
        ]);
    }

    public function test_create_user_with_same_idempotency_key_returns_already_provisioned(): void
    {
        $payload = [
            'idempotency_key' => 'idemp-002-dup',
            'name' => 'Duplicate Owner',
            'email' => 'owner.dup@truckbill.test',
            'password' => 'secretPassword123!',
            'role' => 'super_admin',
        ];

        $first = $this->signedRequest('POST', '/api/onboarding/create-user', $payload);
        $first->assertStatus(201);

        $second = $this->signedRequest('POST', '/api/onboarding/create-user', $payload);
        $second->assertOk()
            ->assertJson([
                'status' => 'already_provisioned',
                'email' => 'owner.dup@truckbill.test',
            ]);
    }

    public function test_create_user_rejects_duplicate_email_without_idempotency_log(): void
    {
        User::query()->create([
            'name' => 'Existing User',
            'email' => 'existing@truckbill.test',
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $payload = [
            'idempotency_key' => 'idemp-different-key',
            'name' => 'Another User',
            'email' => 'existing@truckbill.test',
            'password' => 'secretPassword123!',
            'role' => 'super_admin',
        ];

        $response = $this->signedRequest('POST', '/api/onboarding/create-user', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_update_user_updates_super_admin_details(): void
    {
        $user = User::query()->create([
            'name' => 'Original Name',
            'email' => 'update.target@truckbill.test',
            'password' => bcrypt('oldPassword123'),
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);

        $payload = [
            'email' => 'update.target@truckbill.test',
            'name' => 'Updated Name',
            'password' => 'newPassword1234!',
            'email_verified_at' => now()->toDateTimeString(),
            'policy_accepted_at' => now()->toDateTimeString(),
        ];

        $response = $this->signedRequest('POST', '/api/onboarding/update-user', $payload);

        $response->assertOk()
            ->assertJsonPath('exists', true)
            ->assertJsonPath('email', 'update.target@truckbill.test')
            ->assertJsonPath('email_verified', true)
            ->assertJsonPath('policy_accepted', true)
            ->assertJsonPath('status', 'active');

        $this->assertDatabaseHas('users', [
            'email' => 'update.target@truckbill.test',
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_user_fails_if_user_not_found(): void
    {
        $payload = [
            'email' => 'nonexistent@truckbill.test',
            'name' => 'Nobody',
        ];

        $response = $this->signedRequest('POST', '/api/onboarding/update-user', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_status_returns_user_status_by_email(): void
    {
        $user = User::query()->create([
            'name' => 'Pending User',
            'email' => 'pending@truckbill.test',
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
            'email_verified_at' => null,
            'policy_accepted_at' => null,
        ]);

        $response = $this->signedRequest('GET', '/api/onboarding/status?email=pending@truckbill.test');

        $response->assertOk()
            ->assertJson([
                'exists' => true,
                'email' => 'pending@truckbill.test',
                'status' => 'pending_email_verification',
            ]);

        // When email is verified but policy not accepted
        $user->update(['email_verified_at' => now()]);
        $response2 = $this->signedRequest('GET', '/api/onboarding/status?email=pending@truckbill.test');
        $response2->assertOk()->assertJsonPath('status', 'pending_policy_acceptance');

        // When both verified and accepted
        $user->update(['policy_accepted_at' => now()]);
        $response3 = $this->signedRequest('GET', '/api/onboarding/status?email=pending@truckbill.test');
        $response3->assertOk()->assertJsonPath('status', 'active');

        // Unknown email
        $responseUnknown = $this->signedRequest('GET', '/api/onboarding/status?email=unknown@truckbill.test');
        $responseUnknown->assertOk()
            ->assertJson([
                'exists' => false,
                'email' => 'unknown@truckbill.test',
            ]);

        // When user is not super admin, status is inactive
        $user->update(['user_role_id' => UserRole::ADMIN]);
        $responseInactive = $this->signedRequest('GET', '/api/onboarding/status?email=pending@truckbill.test');
        $responseInactive->assertOk()->assertJsonPath('status', 'inactive');

        // Direct call to statusFor(null)
        $service = app(\App\Services\OnboardingProvisioningService::class);
        $this->assertSame(['exists' => false, 'email' => null], $service->statusFor(null));
    }

    public function test_request_fails_with_invalid_or_missing_signature(): void
    {
        $response = $this->postJson('/api/onboarding/create-user', [
            'idempotency_key' => 'key-1',
            'name' => 'Hack',
            'email' => 'hack@test.com',
            'password' => 'password123',
            'role' => 'super_admin',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Missing signature headers.']);
    }
}
