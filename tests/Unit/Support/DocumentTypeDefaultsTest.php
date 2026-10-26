<?php

namespace Tests\Unit\Support;

use App\DocumentType;
use App\Support\DocumentTypeDefaults;
use App\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTypeDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $slug = 'doc-tenant'): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Doc Co',
            'slug' => $slug,
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);
    }

    public function test_presets_and_code_generation(): void
    {
        $presets = DocumentTypeDefaults::presets();
        $this->assertNotEmpty($presets);

        $tenant = $this->createTenant('doc-code-tenant');
        $this->assertSame('fitness_certificate', DocumentTypeDefaults::generateCode('Fitness Certificate', $tenant->id));
        $this->assertSame('type', DocumentTypeDefaults::generateCode('', $tenant->id));
    }

    public function test_seed_and_label_caching(): void
    {
        $tenant = $this->createTenant('doc-seed-tenant');
        DocumentTypeDefaults::seedForTenant($tenant->id);

        $labels = DocumentTypeDefaults::labelsForTenant($tenant->id);
        $this->assertNotEmpty($labels);

        $firstCode = array_key_first($labels);
        $this->assertSame($labels[$firstCode], DocumentTypeDefaults::labelFor($tenant->id, $firstCode));

        // Test forgetting specific tenant and all cache
        DocumentTypeDefaults::forgetLabelCache($tenant->id);
        DocumentTypeDefaults::forgetLabelCache();

        // Calling seedForTenant a second time returns early (already seeded)
        DocumentTypeDefaults::seedForTenant($tenant->id);

        // Code collisions append suffix _1
        $code1 = DocumentTypeDefaults::generateCode('Fitness Certificate', $tenant->id);
        $this->assertSame('fitness_certificate_1', $code1);

        // When ignoring the existing record, code generation does not collision
        $existing = DocumentType::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('code', 'fitness_certificate')->first();
        $codeNoCollision = DocumentTypeDefaults::generateCode('Fitness Certificate', $tenant->id, (int) $existing->id);
        $this->assertSame('fitness_certificate', $codeNoCollision);
    }
}
