<?php

namespace App\Services;

use App\OnboardingProvisioningLog;
use App\User;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnboardingProvisioningService
{
    public function createUser(array $payload): array
    {
        $existingLog = OnboardingProvisioningLog::query()
            ->where('idempotency_key', $payload['idempotency_key'])
            ->first();

        if ($existingLog !== null && $existingLog->response_payload !== null) {
            $response = $existingLog->response_payload;

            if (($response['status'] ?? null) === 'created') {
                $response['status'] = 'already_provisioned';
            }

            return $response;
        }

        if (User::query()->withoutGlobalScope('tenant')->where('email', $payload['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'The email has already been taken.',
            ]);
        }

        return DB::transaction(function () use ($payload): array {
            $user = User::query()->create([
                'tenant_id' => null,
                'name' => $payload['name'],
                'email' => $payload['email'],
                'password' => $payload['password'],
                'user_role_id' => UserRole::SUPER_ADMIN,
                'email_verified_at' => $payload['email_verified_at'] ?? null,
                'policy_accepted_at' => $payload['policy_accepted_at'] ?? null,
            ]);

            $response = [
                'status' => 'created',
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => 'super_admin',
            ];

            OnboardingProvisioningLog::query()->updateOrCreate(
                ['idempotency_key' => $payload['idempotency_key']],
                [
                    'action' => 'create_user',
                    'user_id' => $user->id,
                    'request_payload' => $this->redactPayload($payload),
                    'response_payload' => $response,
                ]
            );

            return $response;
        });
    }

    public function updateUser(array $payload): array
    {
        $user = User::query()
            ->withoutGlobalScope('tenant')
            ->with(['userRole', 'ownedTenants'])
            ->where('email', $payload['email'])
            ->first();

        if ($user === null || ! $user->hasRole('super_admin')) {
            throw ValidationException::withMessages([
                'email' => 'Super admin user not found.',
            ]);
        }

        $updates = collect($payload)
            ->only(['name', 'email_verified_at', 'policy_accepted_at'])
            ->filter(fn ($value) => $value !== null)
            ->all();

        if (! empty($payload['password'])) {
            $updates['password'] = $payload['password'];
        }

        if ($updates !== []) {
            $user->update($updates);
        }

        return $this->statusFor($user->fresh(['userRole', 'ownedTenants']));
    }

    public function statusFor(?User $user): array
    {
        if ($user === null) {
            return [
                'exists' => false,
                'email' => null,
            ];
        }

        return [
            'exists' => true,
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->userRole?->name,
            'email_verified' => $user->email_verified_at !== null,
            'policy_accepted' => $user->policy_accepted_at !== null,
            'tenant_count' => $user->ownedTenants()->count(),
            'status' => $this->resolveStatus($user),
        ];
    }

    public function statusByEmail(string $email): array
    {
        $user = User::query()
            ->withoutGlobalScope('tenant')
            ->with(['userRole', 'ownedTenants'])
            ->where('email', $email)
            ->first();

        if ($user === null) {
            return [
                'exists' => false,
                'email' => $email,
            ];
        }

        return $this->statusFor($user);
    }

    private function resolveStatus(User $user): string
    {
        if ($user->userRole?->name !== 'super_admin') {
            return 'inactive';
        }

        if ($user->email_verified_at === null) {
            return 'pending_email_verification';
        }

        if ($user->policy_accepted_at === null) {
            return 'pending_policy_acceptance';
        }

        return 'active';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactPayload(array $payload): array
    {
        unset($payload['password']);

        return $payload;
    }
}
