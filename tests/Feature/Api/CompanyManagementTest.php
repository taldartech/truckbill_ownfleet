<?php

namespace Tests\Feature\Api;

use App\Branch;
use App\Driver;
use App\Support\BranchProvisioner;
use App\Support\SelectedBranchSession;
use App\Support\SelectedCompanySession;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_super_admin_can_create_and_enter_owned_company(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'ABC Transport',
                'gst_number' => '27AAAAA0000A1Z5',
                'pan_number' => 'ABCDE1234F',
                'contact_person' => 'ABC Contact',
                'contact_number' => '9876543211',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'ABC Transport')
            ->assertJsonPath('data.tenant_id', fn ($id) => $id !== null)
            ->assertJsonPath('data.company_id', fn ($id) => $id !== null);

        $tenantId = Tenant::query()->where('owner_user_id', $superAdmin->id)->value('id');

        $this->assertNotNull($tenantId);
        $this->assertNull(Branch::query()->where('company_id', $tenantId)->first());

        $branchResponse = $this->actingAs($superAdmin)
            ->postJson('/app/api/branches', [
                'company_id' => $tenantId,
                'branch_name' => 'Main Branch',
                'branch_contact_number' => '9876543210',
                'head_office' => true,
            ])
            ->assertCreated();

        $branchId = (int) $branchResponse->json('data.id');

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches/'.$branchId.'/enter')
            ->assertOk()
            ->assertJsonPath('selected_company_id', $tenantId)
            ->assertJsonPath('selected_tenant_id', $tenantId)
            ->assertJsonPath('selected_branch_id', $branchId);

        $this->assertSame($tenantId, SelectedCompanySession::id());
        $this->assertSame($branchId, SelectedBranchSession::id());
        $this->assertSame($tenantId, $superAdmin->fresh()->company_id);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/drivers', [
                'name' => 'Scoped Driver',
                'phone' => '9888888881',
                'license_number' => 'DLSCOPE0012345',
                'joining_date' => '2024-01-15',
                'daily_wage' => 500,
                'is_active' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('drivers', [
            'tenant_id' => $tenantId,
            'name' => 'Scoped Driver',
        ]);
    }

    public function test_super_admin_dashboard_stats_after_enter_do_not_require_company(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Stats Co');
        $branch = BranchProvisioner::ensureDefaultBranch($tenant);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/branches/'.$branch->id.'/enter')
            ->assertOk();

        $this->actingAs($superAdmin)
            ->getJson('/app/dashboard/stats')
            ->assertOk()
            ->assertJsonStructure([
                'fleet' => [
                    'vehicles_count',
                    'drivers_count',
                    'present_today_count',
                ],
            ]);
    }

    public function test_super_admin_without_company_context_cannot_create_driver(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)
            ->postJson('/app/api/drivers', [
                'name' => 'Blocked Driver',
                'phone' => '9888888882',
                'license_number' => 'DLBLOCK0012345',
                'joining_date' => '2024-01-15',
                'daily_wage' => 500,
                'is_active' => true,
            ])
            ->assertForbidden();
    }

    public function test_super_admin_sees_only_owned_companies(): void
    {
        $superAdminA = $this->createSuperAdmin();
        $superAdminB = $this->createSuperAdmin();

        Tenant::query()->create([
            'owner_user_id' => $superAdminA->id,
            'name' => 'Company A',
            'slug' => 'company-a',
            'short_name' => 'CA',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        Tenant::query()->create([
            'owner_user_id' => $superAdminB->id,
            'name' => 'Company B',
            'slug' => 'company-b',
            'short_name' => 'CB',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdminA)
            ->getJson('/app/api/companies');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();

        $this->assertSame(['Company A'], $names);
    }

    public function test_super_admin_exit_clears_company_context(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Exit Co');
        $branch = BranchProvisioner::ensureDefaultBranch($tenant);

        $this->enterBranchForSuperAdmin($superAdmin, $branch);

        $this->actingAs($superAdmin)
            ->postJson('/app/api/companies/exit')
            ->assertOk();

        $this->assertNull(SelectedCompanySession::id());
        $this->assertNull(SelectedBranchSession::id());
        $this->assertNull($superAdmin->fresh()->company_id);
    }

    public function test_master_admin_can_list_all_companies_and_block(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Block Me');
        $masterAdmin = $this->createMasterAdmin();

        $this->actingAs($masterAdmin)
            ->getJson('/app/api/companies')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Block Me']);

        $this->actingAs($masterAdmin)
            ->putJson('/app/api/companies/'.$tenant->id, ['is_blocked' => true])
            ->assertOk()
            ->assertJsonPath('data.is_blocked', true);
    }

    public function test_master_admin_can_set_company_payroll_pay_days_mode(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Pay Days Co');
        $masterAdmin = $this->createMasterAdmin();

        $this->actingAs($masterAdmin)
            ->putJson('/app/api/companies/'.$tenant->id, [
                'payroll_pay_days_mode' => 'fixed_30',
            ])
            ->assertOk()
            ->assertJsonPath('data.payroll_pay_days_mode', 'fixed_30');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'payroll_pay_days_mode' => 'fixed_30',
        ]);
    }

    public function test_tenant_id_and_company_id_alias_match_on_driver(): void
    {
        [$tenant, $admin] = $this->createTenantAdmin();

        $driver = Driver::query()->create([
            ...$this->operationalAttributesForTenant($tenant),
            'name' => 'Alias Driver',
            'phone' => '9000000001',
            'license_number' => 'DL-ALIAS',
            'daily_wage' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->getJson('/app/api/drivers/'.$driver->id)
            ->assertOk()
            ->assertJsonPath('data.tenant_id', $tenant->id);

        $this->assertSame($admin->company_id, $tenant->id);
    }

    public function test_cannot_create_company_without_pan_and_contact_number(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'No Identity Co',
                'gst_number' => '27AAAAA0000A1Z5',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['pan_number', 'contact_number', 'contact_person']);
    }

    public function test_cannot_create_company_with_only_pan_number(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'PAN Only Co',
                'pan_number' => 'ABCDE1234F',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['contact_number', 'contact_person']);
    }

    public function test_cannot_update_company_to_remove_pan_or_contact_number(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Identity Co', [
            'gst_number' => '27AAAAA0000A1Z5',
            'pan_number' => 'ABCDE1234F',
            'contact_person' => 'Identity Contact',
            'contact_number' => '9876543210',
        ]);

        $response = $this->actingAs($superAdmin)
            ->putJson('/app/api/companies/'.$tenant->id, [
                'gst_number' => '27AAAAA0000A1Z5',
                'pan_number' => '',
                'contact_person' => '',
                'contact_number' => '',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['pan_number', 'contact_number', 'contact_person']);
    }

    public function test_cannot_create_company_with_duplicate_gst_or_pan(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'First Transport',
                'gst_number' => '27AAAAA0000A1Z5',
                'pan_number' => 'ABCDE1234F',
                'contact_person' => 'First Contact',
                'contact_number' => '9876543212',
            ])
            ->assertCreated();

        $duplicate = $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'Second Transport',
                'gst_number' => '27aaaaa0000a1z5',
                'pan_number' => 'abcde1234f',
                'contact_person' => 'Second Contact',
                'contact_number' => '9876543213',
            ]);

        $duplicate->assertUnprocessable();
        $duplicate->assertJsonValidationErrors(['gst_number', 'pan_number']);
    }

    public function test_company_can_keep_its_own_gst_and_pan_on_update(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $tenant = $this->createOwnedTenant($superAdmin, 'Update Co');

        $tenant->update([
            'gst_number' => '27AAAAA0000A1Z5',
            'pan_number' => 'ABCDE1234F',
            'contact_person' => 'Update Contact',
            'contact_number' => '9876543214',
        ]);

        $this->actingAs($superAdmin)
            ->putJson('/app/api/companies/'.$tenant->id, [
                'name' => 'Updated Co',
                'gst_number' => '27AAAAA0000A1Z5',
                'pan_number' => 'ABCDE1234F',
                'contact_person' => 'Update Contact',
                'contact_number' => '9876543214',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Co');
    }

    public function test_cannot_update_company_to_use_another_companys_gst_or_pan(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->createOwnedTenant($superAdmin, 'Existing Co', [
            'gst_number' => '27AAAAA0000A1Z5',
            'pan_number' => 'ABCDE1234F',
        ]);

        $otherTenant = $this->createOwnedTenant($superAdmin, 'Other Co');

        $response = $this->actingAs($superAdmin)
            ->putJson('/app/api/companies/'.$otherTenant->id, [
                'gst_number' => '27AAAAA0000A1Z5',
                'pan_number' => 'ABCDE1234F',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['gst_number', 'pan_number']);
    }

    public function test_cannot_create_company_with_invalid_or_duplicate_contact_number(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'Contact Co',
                'pan_number' => 'ABCDE1234G',
                'contact_person' => 'Contact Person',
                'contact_number' => '9876543210',
            ])
            ->assertCreated();

        $invalidFormat = $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'Bad Contact Co',
                'pan_number' => 'ABCDE1234H',
                'contact_number' => '12345',
            ]);

        $invalidFormat->assertUnprocessable();
        $invalidFormat->assertJsonValidationErrors(['contact_number']);

        $duplicate = $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'Duplicate Contact Co',
                'pan_number' => 'ABCDE1234I',
                'contact_number' => '98765 43210',
            ]);

        $duplicate->assertUnprocessable();
        $duplicate->assertJsonValidationErrors(['contact_number']);
    }

    public function test_company_logo_can_be_uploaded_replaced_and_removed(): void
    {
        Storage::fake('public');

        $superAdmin = $this->createSuperAdmin();
        $logo = UploadedFile::fake()->image('company-logo.png', 120, 120);

        $createResponse = $this->actingAs($superAdmin)
            ->post('/app/api/companies', [
                'name' => 'Logo Transport',
                'gst_number' => '27BBBBB0000B1Z5',
                'pan_number' => 'BBBBB1234B',
                'contact_person' => 'Logo Contact',
                'contact_number' => '9876543291',
                'company_logo' => $logo,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertCreated();

        $companyId = $createResponse->json('data.id');
        $storedLogo = $createResponse->json('data.company_logo');

        $this->assertNotNull($storedLogo);
        $this->assertNotNull($createResponse->json('data.logo_url'));
        Storage::disk('public')->assertExists($storedLogo);

        $company = Tenant::query()->findOrFail($companyId);
        $this->assertNotNull($company->logoPublicUrl());
        $this->assertNotNull($company->logoFilesystemPathForPdf());

        $replacement = UploadedFile::fake()->image('company-logo-2.jpg', 140, 140);

        $updateResponse = $this->actingAs($superAdmin)
            ->post('/app/api/companies/'.$companyId, [
                '_method' => 'PUT',
                'name' => 'Logo Transport',
                'pan_number' => 'BBBBB1234B',
                'contact_person' => 'Logo Contact',
                'contact_number' => '9876543291',
                'company_logo' => $replacement,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk();

        $newLogo = $updateResponse->json('data.company_logo');
        $this->assertNotSame($storedLogo, $newLogo);
        Storage::disk('public')->assertMissing($storedLogo);
        Storage::disk('public')->assertExists($newLogo);

        $this->actingAs($superAdmin)
            ->post('/app/api/companies/'.$companyId, [
                '_method' => 'PUT',
                'name' => 'Logo Transport',
                'pan_number' => 'BBBBB1234B',
                'contact_person' => 'Logo Contact',
                'contact_number' => '9876543291',
                'remove_logo' => '1',
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('data.company_logo', null)
            ->assertJsonPath('data.logo_url', null);

        Storage::disk('public')->assertMissing($newLogo);
    }

    public function test_company_digital_signature_can_be_uploaded_replaced_and_removed(): void
    {
        Storage::fake('public');

        $superAdmin = $this->createSuperAdmin();
        $companyFields = [
            'name' => 'Signature Transport',
            'pan_number' => 'SSSSS1234S',
            'contact_person' => 'Signature Contact',
            'contact_number' => '9876543281',
        ];

        $createResponse = $this->actingAs($superAdmin)
            ->post('/app/api/companies', [
                ...$companyFields,
                'digital_signature' => UploadedFile::fake()->image('signature.png', 300, 100),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertCreated();

        $companyId = $createResponse->json('data.id');
        $storedSignature = $createResponse->json('data.digital_signature');

        $this->assertStringStartsWith("signatures/companies/{$companyId}/", $storedSignature);
        $this->assertNotNull($createResponse->json('data.signature_url'));
        Storage::disk('public')->assertExists($storedSignature);
        $this->assertStringStartsWith(
            'data:image/png;base64,',
            Tenant::query()->findOrFail($companyId)->signatureDataUriForPdf(),
        );

        $this->actingAs($superAdmin)
            ->putJson('/app/api/companies/'.$companyId, [
                ...$companyFields,
                'name' => 'Signature Transport Renamed',
            ])
            ->assertOk()
            ->assertJsonPath('data.digital_signature', $storedSignature);

        $updateResponse = $this->actingAs($superAdmin)
            ->post('/app/api/companies/'.$companyId, [
                ...$companyFields,
                '_method' => 'PUT',
                'digital_signature' => UploadedFile::fake()->image('signature-2.jpg', 300, 100),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk();

        $newSignature = $updateResponse->json('data.digital_signature');
        $this->assertNotSame($storedSignature, $newSignature);
        Storage::disk('public')->assertMissing($storedSignature);
        Storage::disk('public')->assertExists($newSignature);

        $this->actingAs($superAdmin)
            ->post('/app/api/companies/'.$companyId, [
                ...$companyFields,
                '_method' => 'PUT',
                'remove_signature' => '1',
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJsonPath('data.digital_signature', null)
            ->assertJsonPath('data.signature_url', null);

        Storage::disk('public')->assertMissing($newSignature);
        $this->assertNull(Tenant::query()->findOrFail($companyId)->signatureDataUriForPdf());
    }

    public function test_company_digital_signature_rejects_unsupported_files(): void
    {
        Storage::fake('public');

        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)
            ->post('/app/api/companies', [
                'name' => 'Bad Signature Co',
                'pan_number' => 'SSSSS1234T',
                'contact_person' => 'Bad Contact',
                'contact_number' => '9876543282',
                'digital_signature' => UploadedFile::fake()->create('signature.pdf', 50, 'application/pdf'),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['digital_signature']);

        $this->actingAs($superAdmin)
            ->post('/app/api/companies', [
                'name' => 'Large Signature Co',
                'pan_number' => 'SSSSS1234U',
                'contact_person' => 'Large Contact',
                'contact_number' => '9876543283',
                'digital_signature' => UploadedFile::fake()->image('signature.png')->size(76),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['digital_signature']);
    }

    public function test_company_document_color_can_be_set_and_updated(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $company = $this->createOwnedTenant($superAdmin, 'Color Transport', [
            'pan_number' => 'CCCCC1234C',
            'contact_person' => 'Color Contact',
            'contact_number' => '9876543292',
            'document_color' => null,
        ]);

        $this->assertNull($company->documentColor());

        $this->actingAs($superAdmin)
            ->getJson('/app/api/companies/'.$company->id)
            ->assertOk()
            ->assertJsonPath('data.document_color', null);

        $this->actingAs($superAdmin)
            ->putJson('/app/api/companies/'.$company->id, [
                'name' => 'Color Transport',
                'pan_number' => 'CCCCC1234C',
                'contact_person' => 'Color Contact',
                'contact_number' => '9876543292',
                'document_color' => '#1f6f8b',
            ])
            ->assertOk()
            ->assertJsonPath('data.document_color', '#1F6F8B');

        $this->assertSame('#1F6F8B', $company->fresh()->documentColor());

        $this->actingAs($superAdmin)
            ->putJson('/app/api/companies/'.$company->id, [
                'name' => 'Color Transport',
                'pan_number' => 'CCCCC1234C',
                'contact_person' => 'Color Contact',
                'contact_number' => '9876543292',
                'document_color' => '',
            ])
            ->assertOk()
            ->assertJsonPath('data.document_color', null);

        $this->assertNull($company->fresh()->documentColor());
    }

    public function test_company_update_keeps_existing_logo_when_no_file_is_provided(): void
    {
        Storage::fake('public');

        $superAdmin = $this->createSuperAdmin();
        $logo = UploadedFile::fake()->image('keep-logo.png', 100, 100);

        $createResponse = $this->actingAs($superAdmin)
            ->post('/app/api/companies', [
                'name' => 'Keep Logo Co',
                'pan_number' => 'CCCCC1234C',
                'contact_person' => 'Keep Contact',
                'contact_number' => '9876543292',
                'company_logo' => $logo,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertCreated();

        $companyId = $createResponse->json('data.id');
        $storedLogo = $createResponse->json('data.company_logo');

        $this->actingAs($superAdmin)
            ->putJson('/app/api/companies/'.$companyId, [
                'name' => 'Keep Logo Co Updated',
                'pan_number' => 'CCCCC1234C',
                'contact_person' => 'Keep Contact',
                'contact_number' => '9876543292',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Keep Logo Co Updated')
            ->assertJsonPath('data.company_logo', $storedLogo);

        Storage::disk('public')->assertExists($storedLogo);
    }

    public function test_company_creation_provisions_trial_subscription_even_when_admin_api_is_unreachable(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)
            ->postJson('/app/api/companies', [
                'name' => 'Resilient Fleet Co',
                'pan_number' => 'ABCDE9999F',
                'contact_person' => 'Fleet Owner',
                'contact_number' => '9876543299',
            ])
            ->assertCreated();

        $tenantId = $response->json('data.id');
        $this->assertNotNull($tenantId);

        $this->assertDatabaseHas('tenant_subscriptions', [
            'tenant_id' => $tenantId,
            'status' => 'trial',
            'included_branches' => 2,
            'users_per_branch' => 3,
            'included_trucks' => 50,
        ]);

        $this->assertNotNull($response->json('data.plan_end_date'));
    }

    public function test_copy_tenant_fleet_command_requires_two_companies(): void
    {
        $superAdmin = $this->createSuperAdmin();
        Tenant::query()->delete();
        $this->createOwnedTenant($superAdmin, 'Only One Co');

        $exitCode = \Illuminate\Support\Facades\Artisan::call('fleet:copy-tenant');
        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('At least two companies are required', \Illuminate\Support\Facades\Artisan::output());
    }

    public function test_copy_tenant_fleet_command_copies_fleet(): void
    {
        $superAdmin = $this->createSuperAdmin();
        Tenant::query()->delete();
        $sourceTenant = $this->createOwnedTenant($superAdmin, 'A Source Co');
        $sourceBranch = BranchProvisioner::ensureDefaultBranch($sourceTenant);
        $targetTenant = $this->createOwnedTenant($superAdmin, 'B Target Co');
        $targetBranch = BranchProvisioner::ensureDefaultBranch($targetTenant);

        $vehicle = \App\Vehicle::query()->create([
            'tenant_id' => $sourceTenant->id,
            'branch_id' => $sourceBranch->id,
            'registration_number' => 'MH12CP0001',
            'vehicle_type' => 'TRUCK',
        ]);

        $driver = Driver::query()->create([
            'tenant_id' => $sourceTenant->id,
            'branch_id' => $sourceBranch->id,
            'name' => 'Copy Driver',
            'phone' => '9876543210',
            'daily_wage' => 700,
            'is_active' => true,
        ]);

        \App\DriverAssignment::query()->create([
            'tenant_id' => $sourceTenant->id,
            'branch_id' => $sourceBranch->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'assigned_from' => '2026-01-01',
            'is_primary' => true,
        ]);

        $this->artisan('fleet:copy-tenant')
            ->expectsChoice('Source company', $sourceTenant->id, [
                $sourceTenant->id => sprintf('%s (%s)', $sourceTenant->name, $sourceTenant->slug),
                $targetTenant->id => sprintf('%s (%s)', $targetTenant->name, $targetTenant->slug),
            ])
            ->expectsChoice('Target company', $targetTenant->id, [
                $targetTenant->id => sprintf('%s (%s)', $targetTenant->name, $targetTenant->slug),
            ])
            ->expectsChoice(
                sprintf('Map source branch "%s" to target branch', sprintf('#%d %s [HO]', $sourceBranch->id, $sourceBranch->branch_name)),
                (string) $targetBranch->id,
                [
                    (string) $targetBranch->id => sprintf('#%d %s [HO]', $targetBranch->id, $targetBranch->branch_name),
                ]
            )
            ->expectsConfirmation('Also copy current primary driver–truck assignments?', 'yes')
            ->expectsConfirmation('Run as dry run (preview only, no writes)?', 'yes')
            ->expectsConfirmation('Proceed with fleet copy?', 'yes')
            ->expectsOutputToContain('Mode: dry run')
            ->expectsOutputToContain('Dry run complete.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('vehicles', [
            'tenant_id' => $targetTenantId = $targetTenant->id,
            'registration_number' => 'MH12CP0001',
        ]);
        $this->assertDatabaseMissing('drivers', [
            'tenant_id' => $targetTenantId,
            'name' => 'Copy Driver',
        ]);
    }

    public function test_copy_tenant_fleet_command_writes_records_in_write_mode(): void
    {
        $superAdmin = $this->createSuperAdmin();
        Tenant::query()->delete();
        $sourceTenant = $this->createOwnedTenant($superAdmin, 'Write Source Co');
        $sourceBranch = BranchProvisioner::ensureDefaultBranch($sourceTenant);
        $targetTenant = $this->createOwnedTenant($superAdmin, 'Write Target Co');
        $targetBranch = BranchProvisioner::ensureDefaultBranch($targetTenant);

        $vehicle = \App\Vehicle::query()->create([
            'tenant_id' => $sourceTenant->id,
            'branch_id' => $sourceBranch->id,
            'registration_number' => 'MH12WR0001',
            'vehicle_type' => 'TRUCK',
        ]);

        $driverWithLicense = Driver::query()->create([
            'tenant_id' => $sourceTenant->id,
            'branch_id' => $sourceBranch->id,
            'name' => 'Licensed Driver',
            'license_number' => 'DL1234567890123',
            'phone' => '9876543211',
            'daily_wage' => 750,
            'is_active' => true,
        ]);

        $driverWithoutLicense = Driver::query()->create([
            'tenant_id' => $sourceTenant->id,
            'branch_id' => $sourceBranch->id,
            'name' => 'Unlicensed Driver',
            'license_number' => null,
            'phone' => '9876543212',
            'daily_wage' => 600,
            'is_active' => true,
        ]);

        \App\DriverAssignment::query()->create([
            'tenant_id' => $sourceTenant->id,
            'branch_id' => $sourceBranch->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driverWithLicense->id,
            'assigned_from' => '2026-01-01',
            'is_primary' => true,
        ]);

        $this->artisan('fleet:copy-tenant')
            ->expectsChoice('Source company', $sourceTenant->id, [
                $sourceTenant->id => sprintf('%s (%s)', $sourceTenant->name, $sourceTenant->slug),
                $targetTenant->id => sprintf('%s (%s)', $targetTenant->name, $targetTenant->slug),
            ])
            ->expectsChoice('Target company', $targetTenant->id, [
                $targetTenant->id => sprintf('%s (%s)', $targetTenant->name, $targetTenant->slug),
            ])
            ->expectsChoice(
                sprintf('Map source branch "%s" to target branch', sprintf('#%d %s [HO]', $sourceBranch->id, $sourceBranch->branch_name)),
                (string) $targetBranch->id,
                [
                    (string) $targetBranch->id => sprintf('#%d %s [HO]', $targetBranch->id, $targetBranch->branch_name),
                ]
            )
            ->expectsConfirmation('Also copy current primary driver–truck assignments?', 'yes')
            ->expectsConfirmation('Run as dry run (preview only, no writes)?', 'no')
            ->expectsConfirmation('Proceed with fleet copy?', 'yes')
            ->expectsOutputToContain('Mode: write')
            ->expectsOutputToContain('Fleet copy complete.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('vehicles', [
            'tenant_id' => $targetTenant->id,
            'registration_number' => 'MH12WR0001',
            'branch_id' => $targetBranch->id,
        ]);

        $this->assertDatabaseHas('drivers', [
            'tenant_id' => $targetTenant->id,
            'license_number' => 'DL1234567890123',
            'branch_id' => $targetBranch->id,
        ]);

        $this->assertDatabaseHas('drivers', [
            'tenant_id' => $targetTenant->id,
            'name' => 'Unlicensed Driver',
            'phone' => '9876543212',
            'branch_id' => $targetBranch->id,
        ]);

        $targetVehicle = \App\Vehicle::withoutGlobalScopes()
            ->where('tenant_id', $targetTenant->id)
            ->where('registration_number', 'MH12WR0001')
            ->firstOrFail();

        $targetDriver = Driver::withoutGlobalScopes()
            ->where('tenant_id', $targetTenant->id)
            ->where('license_number', 'DL1234567890123')
            ->firstOrFail();

        $this->assertDatabaseHas('driver_assignments', [
            'tenant_id' => $targetTenant->id,
            'branch_id' => $targetBranch->id,
            'vehicle_id' => $targetVehicle->id,
            'driver_id' => $targetDriver->id,
            'is_primary' => true,
        ]);

        // Running it a second time should detect existing records and skip them
        $this->artisan('fleet:copy-tenant')
            ->expectsChoice('Source company', $sourceTenant->id, [
                $sourceTenant->id => sprintf('%s (%s)', $sourceTenant->name, $sourceTenant->slug),
                $targetTenant->id => sprintf('%s (%s)', $targetTenant->name, $targetTenant->slug),
            ])
            ->expectsChoice('Target company', $targetTenant->id, [
                $targetTenant->id => sprintf('%s (%s)', $targetTenant->name, $targetTenant->slug),
            ])
            ->expectsChoice(
                sprintf('Map source branch "%s" to target branch', sprintf('#%d %s [HO]', $sourceBranch->id, $sourceBranch->branch_name)),
                (string) $targetBranch->id,
                [
                    (string) $targetBranch->id => sprintf('#%d %s [HO]', $targetBranch->id, $targetBranch->branch_name),
                ]
            )
            ->expectsConfirmation('Also copy current primary driver–truck assignments?', 'yes')
            ->expectsConfirmation('Run as dry run (preview only, no writes)?', 'no')
            ->expectsConfirmation('Proceed with fleet copy?', 'yes')
            ->expectsOutputToContain('Fleet copy complete.')
            ->assertExitCode(0);
    }

    public function test_copy_tenant_fleet_command_can_be_cancelled(): void
    {
        $superAdmin = $this->createSuperAdmin();
        Tenant::query()->delete();
        $sourceTenant = $this->createOwnedTenant($superAdmin, 'Cancel Source Co');
        $sourceBranch = BranchProvisioner::ensureDefaultBranch($sourceTenant);
        $targetTenant = $this->createOwnedTenant($superAdmin, 'Cancel Target Co');
        $targetBranch = BranchProvisioner::ensureDefaultBranch($targetTenant);

        $this->artisan('fleet:copy-tenant')
            ->expectsChoice('Source company', $sourceTenant->id, [
                $sourceTenant->id => sprintf('%s (%s)', $sourceTenant->name, $sourceTenant->slug),
                $targetTenant->id => sprintf('%s (%s)', $targetTenant->name, $targetTenant->slug),
            ])
            ->expectsChoice('Target company', $targetTenant->id, [
                $targetTenant->id => sprintf('%s (%s)', $targetTenant->name, $targetTenant->slug),
            ])
            ->expectsChoice(
                sprintf('Map source branch "%s" to target branch', sprintf('#%d %s [HO]', $sourceBranch->id, $sourceBranch->branch_name)),
                (string) $targetBranch->id,
                [
                    (string) $targetBranch->id => sprintf('#%d %s [HO]', $targetBranch->id, $targetBranch->branch_name),
                ]
            )
            ->expectsConfirmation('Also copy current primary driver–truck assignments?', 'no')
            ->expectsConfirmation('Run as dry run (preview only, no writes)?', 'yes')
            ->expectsConfirmation('Proceed with fleet copy?', 'no')
            ->expectsOutputToContain('Cancelled.')
            ->assertExitCode(0);
    }

    public function test_copy_tenant_fleet_command_fails_if_company_has_no_branches(): void
    {
        $superAdmin = $this->createSuperAdmin();
        Tenant::query()->delete();
        $sourceTenant = $this->createOwnedTenant($superAdmin, 'NoBranch Source Co');
        $targetTenant = $this->createOwnedTenant($superAdmin, 'NoBranch Target Co');
        // Do not provision branches for sourceTenant

        $this->artisan('fleet:copy-tenant')
            ->expectsChoice('Source company', $sourceTenant->id, [
                $sourceTenant->id => sprintf('%s (%s)', $sourceTenant->name, $sourceTenant->slug),
                $targetTenant->id => sprintf('%s (%s)', $targetTenant->name, $targetTenant->slug),
            ])
            ->expectsChoice('Target company', $targetTenant->id, [
                $targetTenant->id => sprintf('%s (%s)', $targetTenant->name, $targetTenant->slug),
            ])
            ->expectsOutputToContain('has no branches')
            ->assertExitCode(1);
    }

    private function createSuperAdmin(): User
    {
        return User::query()->create([
            'tenant_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::SUPER_ADMIN,
        ]);
    }

    private function createMasterAdmin(): User
    {
        return User::query()->create([
            'tenant_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::MASTER_ADMIN,
        ]);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createTenantAdmin(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Staff Tenant',
            'slug' => 'staff-tenant',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::ADMIN,
        ]);

        return [$tenant, $this->assignBranchIfRequired($admin, $tenant)];
    }

    private function createOwnedTenant(User $owner, string $name, array $attributes = []): Tenant
    {
        return Tenant::query()->create([
            'owner_user_id' => $owner->id,
            'name' => $name,
            'slug' => str()->slug($name).'-'.fake()->unique()->numerify('###'),
            'short_name' => Tenant::ensureUniqueShortName($name),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
