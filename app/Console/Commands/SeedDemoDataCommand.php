<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Database\Seeders\TestUserSeeder;
use Illuminate\Console\Command;

class SeedDemoDataCommand extends Command
{
    protected $signature = 'demo:seed {--force : Allow demo seeding in production}';

    protected $description = 'Seed test users, demo fleet data, and demo trips for local development';

    public function handle(): int
    {
        if ($this->laravel->environment('production') && ! $this->option('force')) {
            $this->components->warn('Demo data is disabled in production. Use --force only if you really need it.');

            return self::FAILURE;
        }

        $this->components->info('Seeding demo data (test users, fleet, trips)...');

        $this->call('db:seed', [
            '--class' => DemoDataSeeder::class,
            '--force' => true,
        ]);

        $this->components->info('Demo data ready. Test login password: '.TestUserSeeder::PASSWORD);

        return self::SUCCESS;
    }
}
