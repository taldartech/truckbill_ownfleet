<?php

namespace Tests\Unit\Support;

use App\Support\TruckTypeDefaults;
use App\Tenant;
use App\TruckType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TruckTypeDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $slug = 'truck-tenant'): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Truck Co',
            'slug' => $slug,
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);
    }

    public function test_presets_and_default_code(): void
    {
        $presets = TruckTypeDefaults::presets();
        $this->assertCount(4, $presets);
        $this->assertSame('TRUCK', TruckTypeDefaults::defaultCode());
    }

    public function test_seed_for_tenant_seeds_defaults_idempotently(): void
    {
        $tenant = $this->createTenant('truck-seed-tenant');
        TruckTypeDefaults::seedForTenant($tenant->id);

        $this->assertSame(4, TruckType::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        // Second call should not duplicate
        TruckTypeDefaults::seedForTenant($tenant->id);
        $this->assertSame(4, TruckType::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_generate_code_formats_and_handles_collisions_and_ignore_id(): void
    {
        $tenant = $this->createTenant('truck-code-tenant');
        $this->assertSame('CONTAINER_XL', TruckTypeDefaults::generateCode('Container XL', $tenant->id));
        $this->assertSame('TYPE', TruckTypeDefaults::generateCode('', $tenant->id));

        $existing = TruckType::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Trailer',
            'code' => 'TRAILER',
            'is_active' => true,
        ]);

        $this->assertSame('TRAILER_1', TruckTypeDefaults::generateCode('Trailer', $tenant->id));
        $this->assertSame('TRAILER', TruckTypeDefaults::generateCode('Trailer', $tenant->id, $existing->id));
    }
}
