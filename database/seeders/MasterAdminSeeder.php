<?php

namespace Database\Seeders;

use App\User;
use App\UserRole;
use Illuminate\Database\Seeder;

class MasterAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) config('master_admin.email');
        $name = (string) config('master_admin.name');
        $password = config('master_admin.password');

        if (blank($password)) {
            if (! app()->environment('local')) {
                $this->command?->warn('MASTER_ADMIN_PASSWORD is not set. Skipping master admin seed.');

                return;
            }

            $password = 'password';
            $this->command?->warn('MASTER_ADMIN_PASSWORD not set; using default local password "password".');
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'tenant_id' => null,
                'name' => $name,
                'password' => $password,
                'user_role_id' => UserRole::MASTER_ADMIN,
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info("Master admin ready: {$email}");
    }
}
