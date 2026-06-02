<?php

namespace Tests;

use App\Support\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedRolesAndPermissions(): void
    {
        $this->seed([
            \Database\Seeders\UserRoleSeeder::class,
            \Database\Seeders\PermissionSeeder::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::setTenantId(null);
        $this->flushSession();
    }

    protected function tearDown(): void
    {
        TenantContext::setTenantId(null);

        parent::tearDown();
    }
}
