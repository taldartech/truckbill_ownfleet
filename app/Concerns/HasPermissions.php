<?php

namespace App\Concerns;

trait HasPermissions
{
    /** @var list<string>|null */
    private ?array $permissionSlugsCache = null;

    /**
     * @return list<string>
     */
    public function permissionSlugs(): array
    {
        if ($this->permissionSlugsCache !== null) {
            return $this->permissionSlugsCache;
        }

        $this->loadMissing('userRole.permissions');

        $this->permissionSlugsCache = $this->userRole?->permissions
            ->pluck('slug')
            ->values()
            ->all() ?? [];

        return $this->permissionSlugsCache;
    }

    public function hasPermission(string $slug): bool
    {
        return in_array($slug, $this->permissionSlugs(), true);
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
}
