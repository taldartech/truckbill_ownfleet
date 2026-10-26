<?php

namespace Tests\Unit\Support;

use App\Support\SelectedBranchSession;
use App\Support\SelectedCompanySession;
use Tests\TestCase;

class SelectedSessionManagementTest extends TestCase
{
    public function test_selected_branch_session_operations(): void
    {
        SelectedBranchSession::set(42);
        $this->assertSame(42, SelectedBranchSession::id());

        SelectedBranchSession::set(null);
        $this->assertNull(SelectedBranchSession::id());

        SelectedBranchSession::set(99);
        SelectedBranchSession::forget();
        $this->assertNull(SelectedBranchSession::id());
    }

    public function test_selected_company_session_reads_tenant_and_legacy_keys(): void
    {
        session()->forget([SelectedCompanySession::TENANT_KEY, SelectedCompanySession::LEGACY_COMPANY_KEY]);
        $this->assertNull(SelectedCompanySession::id());

        // Legacy key fallback
        session([SelectedCompanySession::LEGACY_COMPANY_KEY => 'legacy-comp-1']);
        $this->assertSame('legacy-comp-1', SelectedCompanySession::id());

        // Primary tenant key precedence
        session([SelectedCompanySession::TENANT_KEY => 'primary-tenant-2']);
        $this->assertSame('primary-tenant-2', SelectedCompanySession::id());
    }

    public function test_selected_company_session_set_and_forget(): void
    {
        SelectedCompanySession::set('tenant-xyz');
        $this->assertSame('tenant-xyz', session(SelectedCompanySession::TENANT_KEY));
        $this->assertSame('tenant-xyz', session(SelectedCompanySession::LEGACY_COMPANY_KEY));

        SelectedCompanySession::forget();
        $this->assertNull(SelectedCompanySession::id());

        SelectedCompanySession::set('tenant-abc');
        SelectedCompanySession::set(null);
        $this->assertNull(SelectedCompanySession::id());
    }
}
