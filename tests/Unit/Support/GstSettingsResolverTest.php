<?php

namespace Tests\Unit\Support;

use App\Branch;
use App\Enums\GstRegistrationType;
use App\Enums\TaxLiability;
use App\Support\GstSettingsResolver;
use App\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GstSettingsResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_branch_inherits_company_gst_by_default(): void
    {
        $stateId = \App\State::query()->where('code', 'MH')->value('id');

        $company = Tenant::query()->create([
            'name' => 'GST Co',
            'slug' => 'gst-co',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
            'registration_type' => GstRegistrationType::Regular,
            'tax_liability' => TaxLiability::ReverseCharge,
            'gst_number' => '27AAAAA0000A1Z5',
            'state_id' => $stateId,
            'gst_rate' => 5,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'branch_name' => 'HO',
            'branch_contact_number' => '9876543210',
            'head_office' => true,
            'inherit_company_gst' => true,
            'registration_type' => GstRegistrationType::Composition,
            'tax_liability' => TaxLiability::ForwardCharge,
            'gst_number' => '27BBBBB0000B1Z5',
            'gst_rate' => 12,
        ]);

        $settings = (new GstSettingsResolver)->forBranch($branch->load('company.state'));

        $this->assertTrue($settings['inherit_company_gst']);
        $this->assertSame(GstRegistrationType::Regular, $settings['registration_type']);
        $this->assertSame(TaxLiability::ReverseCharge, $settings['tax_liability']);
        $this->assertSame('27AAAAA0000A1Z5', $settings['gst_number']);
        $this->assertSame(5.0, $settings['gst_rate']);
        $this->assertSame((int) $stateId, $settings['state_id']);
        $this->assertSame('company', $settings['source']);
    }

    public function test_branch_can_override_company_gst_when_not_inheriting(): void
    {
        $mh = \App\State::query()->where('code', 'MH')->value('id');
        $gj = \App\State::query()->where('code', 'GJ')->value('id');

        $company = Tenant::query()->create([
            'name' => 'GST Co 2',
            'slug' => 'gst-co-2',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
            'registration_type' => GstRegistrationType::Regular,
            'tax_liability' => TaxLiability::ForwardCharge,
            'gst_number' => '27AAAAA0000A1Z5',
            'state_id' => $mh,
            'gst_rate' => 5,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'branch_name' => 'Override Branch',
            'branch_contact_number' => '9876543211',
            'head_office' => true,
            'inherit_company_gst' => false,
            'registration_type' => GstRegistrationType::Composition,
            'gst_number' => null,
            'state_id' => $gj,
            'gst_rate' => 12,
        ]);

        $settings = (new GstSettingsResolver)->forBranch($branch->load(['company.state', 'state']));

        $this->assertFalse($settings['inherit_company_gst']);
        $this->assertSame(GstRegistrationType::Composition, $settings['registration_type']);
        $this->assertNull($settings['tax_liability']);
        $this->assertNull($settings['gst_rate']);
        $this->assertSame((int) $gj, $settings['state_id']);
        $this->assertSame('branch', $settings['source']);
    }

    public function test_from_company_null_returns_default_structure(): void
    {
        $settings = (new GstSettingsResolver)->fromCompany(null, inherit: true);

        $this->assertTrue($settings['inherit_company_gst']);
        $this->assertNull($settings['registration_type']);
        $this->assertNull($settings['tax_liability']);
        $this->assertNull($settings['gst_number']);
        $this->assertNull($settings['gst_rate']);
        $this->assertNull($settings['state_id']);
        $this->assertNull($settings['state_code']);
        $this->assertSame('company', $settings['source']);
    }

    public function test_branch_lazy_loads_relations_and_handles_string_values(): void
    {
        $mh = \App\State::query()->where('code', 'MH')->value('id');

        $company = Tenant::query()->create([
            'name' => 'Lazy Co',
            'slug' => 'lazy-co',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
            'registration_type' => 'regular',
            'tax_liability' => null,
            'gst_number' => '27AAAAA0000A1Z5',
            'state_id' => $mh,
            'gst_rate' => null,
        ]);

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'branch_name' => 'Lazy Branch',
            'branch_contact_number' => '9876543212',
            'head_office' => false,
            'inherit_company_gst' => false,
            'registration_type' => 'regular',
            'tax_liability' => 'forward_charge',
            'gst_number' => '27CCCCCC0000C1Z5',
            'state_id' => $mh,
            'gst_rate' => 18.0,
        ]);

        // Branch without loaded relations triggers lazy-loading queries
        $freshBranch = Branch::query()->find($branch->id);
        $settings = (new GstSettingsResolver)->forBranch($freshBranch);

        $this->assertFalse($settings['inherit_company_gst']);
        $this->assertSame(GstRegistrationType::Regular, $settings['registration_type']);
        $this->assertSame(TaxLiability::ForwardCharge, $settings['tax_liability']);
        $this->assertSame(18.0, $settings['gst_rate']);
        $this->assertSame('MH', $settings['state_code']);

        // Test company with empty gst rate and null tax liability defaulting to ForwardCharge
        $companySettings = (new GstSettingsResolver)->fromCompany(Tenant::query()->find($company->id));
        $this->assertSame(TaxLiability::ForwardCharge, $companySettings['tax_liability']);
        $this->assertNull($companySettings['gst_rate']);
    }
}
