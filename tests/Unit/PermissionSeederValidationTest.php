<?php

namespace Tests\Unit;

use App\Support\PermissionCatalog;
use Tests\TestCase;

class PermissionSeederValidationTest extends TestCase
{
    public function test_permission_catalog_passes_validation(): void
    {
        PermissionCatalog::validate();

        $this->assertGreaterThan(80, count(PermissionCatalog::slugs()));
    }

    public function test_every_slug_has_unique_name(): void
    {
        $names = [];

        foreach (PermissionCatalog::definitions() as $slug => $meta) {
            $this->assertArrayNotHasKey($meta['name'], $names, "Duplicate name for slug [{$slug}]");
            $names[$meta['name']] = $slug;
        }
    }
}
