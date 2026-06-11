<?php

namespace App\Providers;

use App\Attendance;
use App\Broker;
use App\Challan;
use App\City;
use App\Customer;
use App\DocumentType;
use App\Driver;
use App\DriverAssignment;
use App\Emi;
use App\Permission;
use App\Policies\AttendancePolicy;
use App\Policies\BrokerPolicy;
use App\Policies\ChallanPolicy;
use App\Policies\CityPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DocumentTypePolicy;
use App\Policies\DriverAssignmentPolicy;
use App\Policies\DriverPolicy;
use App\Policies\EmiPolicy;
use App\Policies\ManagedUserPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\StatePolicy;
use App\Policies\StationPolicy;
use App\Policies\TenantPolicy;
use App\Policies\TripPolicy;
use App\Policies\TruckTypePolicy;
use App\Policies\TyreLayoutPolicy;
use App\Policies\TyrePolicy;
use App\Policies\UserRolePolicy;
use App\Policies\VehicleDocumentPolicy;
use App\Policies\VehiclePolicy;
use App\State;
use App\Station;
use App\Tenant;
use App\Trip;
use App\TruckType;
use App\Tyre;
use App\TyreLayout;
use App\User;
use App\UserRole;
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

        Gate::policy(Challan::class, ChallanPolicy::class);
        Gate::policy(Emi::class, EmiPolicy::class);
        Gate::policy(Driver::class, DriverPolicy::class);
        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(DriverAssignment::class, DriverAssignmentPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(TruckType::class, TruckTypePolicy::class);
        Gate::policy(DocumentType::class, DocumentTypePolicy::class);
        Gate::policy(VehicleDocument::class, VehicleDocumentPolicy::class);
        Gate::policy(Trip::class, TripPolicy::class);
        Gate::policy(Station::class, StationPolicy::class);
        Gate::policy(Broker::class, BrokerPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(State::class, StatePolicy::class);
        Gate::policy(City::class, CityPolicy::class);
        Gate::policy(Tyre::class, TyrePolicy::class);
        Gate::policy(TyreLayout::class, TyreLayoutPolicy::class);
        Gate::policy(UserRole::class, UserRolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(User::class, ManagedUserPolicy::class);

        Route::bind('company', static fn (string $value): Tenant => Tenant::query()->findOrFail($value));
    }
}
