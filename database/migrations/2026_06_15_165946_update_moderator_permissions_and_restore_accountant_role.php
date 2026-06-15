<?php

use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserRoleSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new UserRoleSeeder)->run();
        (new PermissionSeeder)->run();
    }

    public function down(): void
    {
        (new UserRoleSeeder)->run();
        (new PermissionSeeder)->run();
    }
};
