<?php

namespace App\Providers;

use App\Attendance;
use App\Driver;
use App\DriverAssignment;
use App\Policies\AttendancePolicy;
use App\Policies\DriverAssignmentPolicy;
use App\Policies\DriverPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::policy(Driver::class, DriverPolicy::class);
        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(DriverAssignment::class, DriverAssignmentPolicy::class);
    }
}
