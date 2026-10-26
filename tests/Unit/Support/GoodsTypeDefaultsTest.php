<?php

namespace Tests\Unit\Support;

use App\GoodsType;
use App\Support\GoodsTypeDefaults;
use App\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoodsTypeDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private function createTenant(string $slug = 'goods-tenant'): Tenant
    {
        return Tenant::query()->create([
            'name' => 'Goods Co',
            'slug' => $slug,
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);
    }

    public function test_seed_for_tenant_is_safe_noop(): void
    {
        $tenant = $this->createTenant('noop-tenant');
        GoodsTypeDefaults::seedForTenant($tenant->id);
        $this->assertTrue(true);
    }

    public function test_generate_code_formats_snake_case(): void
    {
        $tenant = $this->createTenant('slug-tenant');
        $code = GoodsTypeDefaults::generateCode('Heavy Machinery 100%', $tenant->id);
        $this->assertSame('heavy_machinery100', $code);
    }

    public function test_generate_code_falls_back_to_goods_for_empty_or_special_chars(): void
    {
        $tenant = $this->createTenant('fallback-tenant');
        $this->assertSame('goods', GoodsTypeDefaults::generateCode('', $tenant->id));
        $this->assertSame('goods', GoodsTypeDefaults::generateCode('   ***   ', $tenant->id));
    }

    public function test_generate_code_handles_existing_collisions_and_ignore_id(): void
    {
        $tenant = $this->createTenant('collision-tenant');
        $first = GoodsType::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Steel',
            'code' => 'steel',
            'is_active' => true,
        ]);

        $secondCode = GoodsTypeDefaults::generateCode('Steel', $tenant->id);
        $this->assertSame('steel_1', $secondCode);

        // Updating self with same name should retain 'steel'
        $updateCode = GoodsTypeDefaults::generateCode('Steel', $tenant->id, $first->id);
        $this->assertSame('steel', $updateCode);
    }
}
