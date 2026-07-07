<?php

namespace App\Console\Commands;

use App\Support\PermissionCache;
use App\User;
use Illuminate\Console\Command;

class ClearPermissionCacheCommand extends Command
{
    protected $signature = 'permissions:clear-cache';

    protected $description = 'Flush cached permission slugs for all users';

    public function handle(): int
    {
        PermissionCache::flush();

        User::query()->each(function (User $user): void {
            $user->clearPermissionCache();
        });

        $this->components->info('Permission cache cleared.');

        return self::SUCCESS;
    }
}
