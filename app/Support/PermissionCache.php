<?php

namespace App\Support;

use App\User;

final class PermissionCache
{
    private static int $generation = 0;

    public static function generation(): int
    {
        return self::$generation;
    }

    public static function flush(): void
    {
        self::$generation++;
    }

    public static function clearUserPermissionCache(User $user): void
    {
        $user->clearPermissionCache();
    }
}
