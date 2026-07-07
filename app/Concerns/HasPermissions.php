<?php

namespace App\Concerns;

use App\Support\PermissionAliasResolver;
use App\Support\PermissionCache;

trait HasPermissions
{
    /** @var list<string>|null */
    private ?array $permissionSlugsCache = null;

    private ?int $permissionSlugsCacheGeneration = null;

    /**
     * @return list<string>
     */
    public function permissionSlugs(): array
    {
        $generation = PermissionCache::generation();

        if (
            $this->permissionSlugsCache !== null
            && $this->permissionSlugsCacheGeneration === $generation
        ) {
            return $this->permissionSlugsCache;
        }

        $this->loadMissing('userRole.permissions');

        $this->permissionSlugsCache = $this->userRole?->permissions
            ->pluck('slug')
            ->values()
            ->all() ?? [];

        $this->permissionSlugsCacheGeneration = $generation;

        return $this->permissionSlugsCache;
    }

    public function hasPermission(string $slug): bool
    {
        $owned = $this->permissionSlugs();

        if (in_array($slug, $owned, true)) {
            return true;
        }

        return PermissionAliasResolver::matches($slug, $owned);
    }

    public function hasAnyPermission(array $slugs): bool
    {
        foreach ($slugs as $slug) {
            if ($this->hasPermission($slug)) {
                return true;
            }
        }

        return false;
    }

    public function clearPermissionCache(): void
    {
        $this->permissionSlugsCache = null;
        $this->permissionSlugsCacheGeneration = null;
    }
}
