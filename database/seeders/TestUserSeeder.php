<?php

namespace Database\Seeders;

use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TestUserSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public const EMAIL_DOMAIN = 'test.truckbill.local';

    public function run(): void
    {
        $this->call(UserRoleSeeder::class);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'test-company'],
            [
                'name' => 'Test Company',
                'short_name' => Tenant::ensureUniqueShortName('TESTCO'),
                'timezone' => 'Asia/Kolkata',
                'is_active' => true,
                'is_blocked' => false,
            ],
        );

        $platformRoles = [
            UserRole::MASTER_ADMIN,
            UserRole::SUPER_ADMIN,
        ];

        $tenantRoles = [
            UserRole::ADMIN,
            UserRole::MODERATOR,
            UserRole::ACCOUNTANT,
            UserRole::TRANSPORTER,
            UserRole::CUSTOMER,
        ];

        foreach ($platformRoles as $roleId) {
            $this->seedUser($roleId, null);
        }

        foreach ($tenantRoles as $roleId) {
            $this->seedUser($roleId, $tenant->id);
        }

        $this->command?->info('Test users ready (password: '.self::PASSWORD.')');
        $this->command?->table(
            ['Role', 'Email'],
            collect(array_merge($platformRoles, $tenantRoles))
                ->map(fn (int $roleId): array => [
                    UserRole::NAMES[$roleId],
                    $this->emailFor($roleId),
                ])
                ->all(),
        );
    }

    private function seedUser(int $roleId, ?string $tenantId): void
    {
        $roleName = UserRole::NAMES[$roleId];
        $displayName = Str::headline(str_replace('_', ' ', $roleName));

        User::query()->updateOrCreate(
            ['email' => $this->emailFor($roleId)],
            [
                'tenant_id' => $tenantId,
                'name' => 'Test '.$displayName,
                'password' => self::PASSWORD,
                'user_role_id' => $roleId,
                'email_verified_at' => now(),
            ],
        );
    }

    private function emailFor(int $roleId): string
    {
        return UserRole::NAMES[$roleId].'@'.self::EMAIL_DOMAIN;
    }
}
