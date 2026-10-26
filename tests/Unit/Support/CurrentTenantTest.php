<?php

namespace Tests\Unit\Support;

use App\Support\CurrentTenant;
use App\Support\TenantContext;
use App\User;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CurrentTenantTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantContext::setTenantId(null);
        parent::tearDown();
    }

    public function test_id_returns_tenant_from_tenant_context_first(): void
    {
        TenantContext::setTenantId('tenant-explicit-123');

        $this->assertSame('tenant-explicit-123', CurrentTenant::id());
        $this->assertSame('tenant-explicit-123', CurrentTenant::requireId());
    }

    public function test_id_returns_user_effective_tenant_id_when_context_is_null(): void
    {
        TenantContext::setTenantId(null);

        $user = new User();
        $user->tenant_id = 'tenant-user-456';
        auth()->setUser($user);

        $this->assertSame('tenant-user-456', CurrentTenant::id());
        $this->assertSame('tenant-user-456', CurrentTenant::requireId());
    }

    public function test_id_returns_null_and_require_id_aborts_403_when_no_tenant_available(): void
    {
        TenantContext::setTenantId(null);
        auth()->logout();

        $this->assertNull(CurrentTenant::id());

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Company context is required.');

        try {
            CurrentTenant::requireId();
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            throw $e;
        }
    }
}
