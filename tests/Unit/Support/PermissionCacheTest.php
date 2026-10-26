<?php

namespace Tests\Unit\Support;

use App\Support\PermissionCache;
use App\User;
use Tests\TestCase;

class PermissionCacheTest extends TestCase
{
    public function test_flush_increments_cache_generation(): void
    {
        $initial = PermissionCache::generation();
        PermissionCache::flush();
        $this->assertSame($initial + 1, PermissionCache::generation());
    }

    public function test_clear_user_permission_cache_delegates_to_user_model(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())->method('clearPermissionCache');

        PermissionCache::clearUserPermissionCache($user);
    }
}
