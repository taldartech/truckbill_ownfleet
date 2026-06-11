<?php

namespace Tests\Feature\Api;

use App\DocumentType;
use App\Support\DocumentTypeDefaults;
use App\Support\SelectedCompanySession;
use App\Support\TenantContext;
use App\Tenant;
use App\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_can_list_and_create_document_type(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        $this->actingAs($admin)
            ->getJson('/app/api/document-types')
            ->assertOk()
            ->assertJsonFragment(['code' => 'insurance', 'name' => 'Insurance']);

        $response = $this->actingAs($admin)
            ->postJson('/app/api/document-types', [
                'name' => 'Pollution Certificate',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.code', 'pollution_certificate');
        $response->assertJsonPath('data.name', 'Pollution Certificate');

        $this->assertDatabaseHas('document_types', [
            'tenant_id' => $tenant->id,
            'code' => 'pollution_certificate',
            'name' => 'Pollution Certificate',
        ]);
    }

    public function test_document_type_cannot_be_deleted(): void
    {
        [$tenant, $admin] = $this->createTenantUser('admin');
        DocumentTypeDefaults::seedForTenant($tenant->id);
        SelectedCompanySession::set($tenant->id);
        TenantContext::setTenantId($tenant->id);

        $type = DocumentType::query()->where('code', 'insurance')->firstOrFail();

        $this->actingAs($admin)
            ->deleteJson("/app/api/document-types/{$type->id}")
            ->assertForbidden();
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function createTenantUser(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant '.fake()->company(),
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor($role) ?? UserRole::ADMIN,
        ]);

        return [$tenant, $user];
    }
}
