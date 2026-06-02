<?php

namespace App\Providers;

use App\Attendance;
use App\Driver;
use App\DriverAssignment;
use App\Policies\AttendancePolicy;
use App\Policies\DriverAssignmentPolicy;
use App\Policies\DriverPolicy;
use App\Policies\TenantPolicy;
use App\Policies\VehicleDocumentPolicy;
use App\Policies\VehiclePolicy;
use App\Tenant;
use App\User;
use App\Vehicle;
use App\VehicleDocument;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
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

        Gate::before(function (User $user, string $ability): ?bool {
            if (! preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', $ability)) {
                return null;
            }

            if (! $user->hasPermission($ability)) {
                return false;
            }

            return true;
        });

        Gate::policy(Driver::class, DriverPolicy::class);
        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(DriverAssignment::class, DriverAssignmentPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(VehicleDocument::class, VehicleDocumentPolicy::class);

        Route::bind('company', static fn (string $value): Tenant => Tenant::query()->findOrFail($value));
    }
}
