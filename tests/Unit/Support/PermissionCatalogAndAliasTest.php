<?php

namespace Tests\Unit\Support;

use App\Support\PermissionAliasResolver;
use App\Support\PermissionCatalog;
use Tests\TestCase;

class PermissionCatalogAndAliasTest extends TestCase
{
    public function test_permission_catalog_validation_and_partitions(): void
    {
        PermissionCatalog::validate();

        $slugs = PermissionCatalog::slugs();
        $masterOnly = PermissionCatalog::masterAdminOnlySlugs();
        $superSlugs = PermissionCatalog::superAdminSlugs();

        $this->assertNotEmpty($slugs);
        $this->assertNotEmpty($masterOnly);
        foreach ($masterOnly as $restricted) {
            $this->assertNotContains($restricted, $superSlugs);
        }
    }

    public function test_permission_alias_resolver_synonyms_and_granular_matches(): void
    {
        // Direct synonyms
        $this->assertTrue(PermissionAliasResolver::matches('companies.switch', ['companies.enter']));
        $this->assertTrue(PermissionAliasResolver::matches('attendance_reports.view', ['reports.view']));

        // Granular action satisfied by .manage
        $this->assertTrue(PermissionAliasResolver::matches('trips.create', ['trips.manage']));
        $this->assertTrue(PermissionAliasResolver::matches('trips.edit', ['trips.manage']));

        // Legacy .manage satisfied by granular .create and .edit
        $this->assertTrue(PermissionAliasResolver::matches('trips.manage', ['trips.create', 'trips.edit']));

        // Exact matches
        $this->assertTrue(PermissionAliasResolver::matches('trips.manage', ['trips.manage']));
        $this->assertTrue(PermissionAliasResolver::matches('trips.create', ['trips.create']));
        $this->assertTrue(PermissionAliasResolver::matches('trips.edit', ['trips.edit']));

        // Non-matching permission
        $this->assertFalse(PermissionAliasResolver::matches('invoices.delete', ['trips.view']));
    }
}
