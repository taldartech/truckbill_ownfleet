<?php

namespace App\Support;

use InvalidArgumentException;

final class PermissionCatalog
{
    /**
     * @return array<string, array{name: string, group: string, description: string, display_order: int}>
     */
    public static function definitions(): array
    {
        return PermissionDefinitions::all();
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::definitions());
    }

    public static function validate(): void
    {
        $definitions = self::definitions();
        $names = [];

        foreach ($definitions as $slug => $meta) {
            if ($slug === '' || ! preg_match('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/', $slug)) {
                throw new InvalidArgumentException("Invalid permission slug [{$slug}].");
            }

            if (isset($names[$meta['name']])) {
                throw new InvalidArgumentException("Duplicate permission name [{$meta['name']}].");
            }

            $names[$meta['name']] = true;

            if (trim($meta['description']) === '') {
                throw new InvalidArgumentException("Missing description for permission [{$slug}].");
            }

            if (! isset($meta['display_order'])) {
                throw new InvalidArgumentException("Missing display_order for permission [{$slug}].");
            }
        }
    }
}
